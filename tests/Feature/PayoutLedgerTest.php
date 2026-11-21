<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Shop;
use App\Services\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/ISSUES.md #7 and #11.
 */
class PayoutLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_every_ledger_row_satisfies_gross_minus_commission_equals_net(): void
    {
        $this->assertGreaterThan(0, Payout::count());

        $this->assertSame(
            0,
            Payout::whereRaw('gross - commission <> net')->count(),
            'A payout row does not add up.',
        );
    }

    public function test_commission_uses_the_shops_own_rate(): void
    {
        $inkwell = Shop::where('slug', 'inkwell-press')->firstOrFail();   // 3%
        $halftone = Shop::where('slug', 'halftone-hub')->firstOrFail();   // 1.5%

        $inkwellPayout = $inkwell->payouts()->firstOrFail();
        $halftonePayout = $halftone->payouts()->firstOrFail();

        $this->assertSame(300, $inkwellPayout->commission_rate);
        $this->assertSame(150, $halftonePayout->commission_rate);
        $this->assertSame(
            (int) round($inkwellPayout->gross * 300 / 10000),
            $inkwellPayout->commission,
        );
        $this->assertSame(
            (int) round($halftonePayout->gross * 150 / 10000),
            $halftonePayout->commission,
        );
    }

    public function test_a_paid_callback_marks_the_payment_and_writes_one_ledger_row(): void
    {
        [$job, $payment] = $this->pendingPayment('pi_test_callback_1');

        $this->postJson('/webhooks/paymongo', $this->paidEvent('evt_1', 'pi_test_callback_1', $job->total))
            ->assertOk();

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1, Payout::where('payment_id', $payment->id)->count());

        $payout = $payment->payout;

        $this->assertSame($job->total, $payout->gross);
        $this->assertSame($payout->gross - $payout->commission, $payout->net);
    }

    public function test_replaying_the_same_event_creates_nothing_new(): void
    {
        [$job, $payment] = $this->pendingPayment('pi_test_callback_2');
        $event = $this->paidEvent('evt_2', 'pi_test_callback_2', $job->total);

        $this->postJson('/webhooks/paymongo', $event)->assertOk();
        $this->postJson('/webhooks/paymongo', $event)->assertOk();

        $this->assertSame(1, Payment::where('intent_id', 'pi_test_callback_2')->count());
        $this->assertSame(1, Payout::where('payment_id', $payment->id)->count());
    }

    public function test_a_different_event_id_for_the_same_intent_does_not_double_credit(): void
    {
        [$job, $payment] = $this->pendingPayment('pi_test_callback_3');

        $this->postJson('/webhooks/paymongo', $this->paidEvent('evt_3a', 'pi_test_callback_3', $job->total))->assertOk();
        $this->postJson('/webhooks/paymongo', $this->paidEvent('evt_3b', 'pi_test_callback_3', $job->total))->assertOk();

        // The unique index on payouts.payment_id is what stops the second credit.
        $this->assertSame(1, Payout::where('payment_id', $payment->id)->count());
    }

    public function test_a_callback_for_an_unknown_intent_is_recorded_as_failed(): void
    {
        $this->postJson('/webhooks/paymongo', $this->paidEvent('evt_orphan', 'pi_does_not_exist', 1000))
            ->assertStatus(202);

        $this->assertDatabaseHas('payment_webhooks', [
            'event_id' => 'evt_orphan',
            'status' => 'failed',
        ]);
    }

    public function test_a_bad_signature_is_rejected_when_a_secret_is_configured(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test_secret']);

        [$job] = $this->pendingPayment('pi_test_callback_4');

        $this->postJson('/webhooks/paymongo', $this->paidEvent('evt_4', 'pi_test_callback_4', $job->total))
            ->assertStatus(401);

        $this->assertSame('pending', Payment::where('intent_id', 'pi_test_callback_4')->value('status'));
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test_secret']);

        [$job, $payment] = $this->pendingPayment('pi_test_callback_5');

        $body = json_encode($this->paidEvent('evt_5', 'pi_test_callback_5', $job->total));
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret');

        $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
            ],
            $body,
        )->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_settlement_is_idempotent_when_called_twice(): void
    {
        [, $payment] = $this->pendingPayment('pi_test_callback_6');
        $settlement = app(PaymentSettlement::class);

        $settlement->markPaid($payment);
        $settlement->markPaid($payment->fresh());

        $this->assertSame(1, Payout::where('payment_id', $payment->id)->count());
    }

    /** @return array{0: Job, 1: Payment} */
    private function pendingPayment(string $intentId): array
    {
        $job = Job::where('status', 'quoted')->firstOrFail();

        $payment = Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => $intentId,
            'amount' => $job->total,
            'processing_fee' => 0,
            'commission' => 0,
            'status' => 'pending',
        ]);

        return [$job, $payment];
    }

    private function paidEvent(string $eventId, string $intentId, int $amount): array
    {
        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_'.$eventId,
                        'type' => 'payment',
                        'attributes' => [
                            'payment_intent_id' => $intentId,
                            'amount' => $amount,
                            'status' => 'paid',
                        ],
                    ],
                ],
            ],
        ];
    }
}
