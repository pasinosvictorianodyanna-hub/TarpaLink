<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayMongoWebhook;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\PayMongo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PayMongoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function event(string $intentId, string $type = 'payment.paid'): array
    {
        return [
            'data' => [
                'id' => 'evt_test',
                'type' => 'event',
                'attributes' => [
                    'type' => $type,
                    'data' => [
                        'id' => 'pay_test',
                        'type' => 'payment',
                        'attributes' => ['payment_intent_id' => $intentId, 'status' => 'paid'],
                    ],
                ],
            ],
        ];
    }

    /** A pending payment on an Inkwell job (Growth plan, 3%). */
    private function pendingPayment(): Payment
    {
        $job = Job::where('shop_id', Shop::where('slug', 'inkwell-press')->value('id'))
            ->where('status', 'quoted')
            ->firstOrFail();

        return Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_sim_'.$job->reference,
            'amount' => 203680,
            'processing_fee' => 6592,
            'commission' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_a_valid_webhook_marks_the_payment_paid_at_the_shop_rate(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson('/webhooks/paymongo', $this->event($payment->intent_id))
            ->assertOk();

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        // Inkwell is on Growth: 3% of ₱2,036.80 = ₱61.10.
        $this->assertSame(6110, $payment->commission);

        // The processor takes 2.5% + ₱15: 5092 + 1500 = ₱65.92, and the shop
        // bears it, so it comes out of the payout.
        $this->assertSame(6592, $payment->processing_fee);
        $this->assertSame(203680 - 6110 - 6592, $payment->net());
    }

    /** Issue #11 acceptance: a replayed webhook must not double-credit. */
    public function test_replaying_the_same_webhook_creates_one_payment_row(): void
    {
        $payment = $this->pendingPayment();
        $event = $this->event($payment->intent_id);

        $this->postJson('/webhooks/paymongo', $event)->assertOk();
        $first = $payment->fresh();

        $this->postJson('/webhooks/paymongo', $event)->assertOk();
        $second = $payment->fresh();

        $this->assertSame(1, Payment::where('intent_id', $payment->intent_id)->count());
        $this->assertSame($first->commission, $second->commission);
        $this->assertEquals($first->paid_at, $second->paid_at);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson(
            '/webhooks/paymongo',
            $this->event($payment->intent_id),
            ['Paymongo-Signature' => 't=1700000000,te=deadbeef']
        )->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_the_controller_queues_rather_than_processing_inline(): void
    {
        Queue::fake();

        $payment = $this->pendingPayment();

        $this->postJson('/webhooks/paymongo', $this->event($payment->intent_id))->assertOk();

        Queue::assertPushed(ProcessPayMongoWebhook::class);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_checkout_uses_the_shops_own_rate_not_a_literal(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
        $shop = Shop::where('slug', 'halftone-hub')->firstOrFail(); // Pro, 1.5%

        $this->actingAs($customer)->post("/shops/{$shop->slug}/checkout", [
            'service_id' => $shop->services->first()->id,
            'width_in' => 4,
            'height_in' => 4,
            'qty' => 100,
            'payment_method' => 'gcash',
        ])->assertRedirect();

        $payment = Payment::latest('id')->firstOrFail();

        $this->assertSame((int) round($payment->amount * 150 / 10000), $payment->commission);
        $this->assertStringStartsWith('pi_sim_', $payment->intent_id);
        $this->assertSame('pending', $payment->status);
    }

    public function test_a_webhook_that_never_matches_is_marked_failed(): void
    {
        $payment = $this->pendingPayment();
        $job = new ProcessPayMongoWebhook($this->event($payment->intent_id));

        // Simulate the queue giving up after the final attempt.
        $job->failed(new RuntimeException('Gateway unreachable'));

        $payment->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
        $this->assertSame('Gateway unreachable', $payment->last_error);
        $this->assertTrue(Payment::needsAttention()->whereKey($payment->id)->exists());
    }

    public function test_the_signature_check_matches_paymongos_scheme(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test']);

        $payload = '{"hello":"world"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $paymongo = app(PayMongo::class);

        $this->assertTrue($paymongo->verifySignature($payload, "t={$timestamp},te={$signature}"));
        $this->assertFalse($paymongo->verifySignature($payload, "t={$timestamp},te=bad"));
        $this->assertFalse($paymongo->verifySignature($payload, null));

        // A correct signature on an old payload is a replay, not a payment.
        $old = '1700000000';
        $oldSignature = hash_hmac('sha256', $old.'.'.$payload, 'whsec_test');

        $this->assertFalse($paymongo->verifySignature($payload, "t={$old},te={$oldSignature}"));
    }

    public function test_a_malformed_event_cannot_fail_unrelated_payments(): void
    {
        $payment = $this->pendingPayment();

        // With no intent in the payload the guard returns before touching the
        // table. Without it, `where('intent_id', null)` becomes `IS NULL` and a
        // garbage event issues a bulk update on a half-configured deployment.
        $job = new ProcessPayMongoWebhook(['data' => ['attributes' => []]]);
        $job->failed(new RuntimeException('No intent in this event'));

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->failed_at);
    }
}
