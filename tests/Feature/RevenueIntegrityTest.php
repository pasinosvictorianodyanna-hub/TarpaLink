<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayMongoWebhook;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\PayMongo;
use App\Services\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four places the revenue model was telling itself a comfortable story.
 *
 * Each one is the same shape: a number that something wrote, and nothing that
 * ever checked it. A fee guessed at checkout and never corrected. Commission
 * counted on work that was not finished. A refund that left the platform out of
 * pocket with no record. Money captured for a job that was cancelled, which
 * nobody gave back.
 */
class RevenueIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function event(string $intentId, ?int $fee = null, string $type = 'payment.paid'): array
    {
        return [
            'data' => [
                'id' => 'evt_revenue',
                'type' => 'event',
                'attributes' => [
                    'type' => $type,
                    'data' => [
                        'id' => 'pay_revenue',
                        'type' => 'payment',
                        'attributes' => array_filter([
                            'payment_intent_id' => $intentId,
                            'status' => 'paid',
                            'fee' => $fee,
                        ], fn ($value) => $value !== null),
                    ],
                ],
            ],
        ];
    }

    /** A pending payment on an Inkwell job, with a fee estimate already written. */
    private function pendingPayment(): Payment
    {
        $job = Job::where('shop_id', Shop::where('slug', 'inkwell-press')->value('id'))
            ->where('status', 'quoted')
            ->firstOrFail();

        return Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_sim_'.$job->reference,
            'amount' => 203_680,
            'processing_fee' => Payment::estimateFee(203_680),
            'commission' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_the_fee_estimate_is_configuration_not_a_magic_number(): void
    {
        // 2.5% + ₱15 by default, which is what the two literals in the checkout
        // controller used to say.
        $this->assertSame(6_592, Payment::estimateFee(203_680));

        config(['services.paymongo.fee_rate_bp' => 300, 'services.paymongo.fee_flat' => 1_000]);

        // The shop bears this fee, so the rate has to be findable and changeable
        // without editing a controller.
        $this->assertSame(7_110, Payment::estimateFee(203_680));
    }

    public function test_an_empty_fee_setting_falls_back_instead_of_charging_nothing(): void
    {
        // `PAYMONGO_FEE_RATE_BP=` in a .env file yields '', not null, and
        // `(int) ''` is 0 — which would drop the percentage and credit the shop
        // the difference without anything failing.
        config(['services.paymongo.fee_rate_bp' => '', 'services.paymongo.fee_flat' => '']);

        $this->assertSame(6_592, Payment::estimateFee(203_680));
    }

    public function test_a_settled_payment_takes_the_processors_fee(): void
    {
        $payment = $this->pendingPayment();

        $this->assertTrue($payment->fee_is_estimate, 'A fee written at checkout is an estimate.');

        (new ProcessPayMongoWebhook($this->event($payment->intent_id, fee: 7_100)))
            ->handle(app(PayMongo::class));

        $fresh = $payment->fresh();

        $this->assertSame('paid', $fresh->status);
        $this->assertSame(7_100, $fresh->processing_fee, 'The provider\'s number replaces the estimate.');
        $this->assertFalse($fresh->fee_is_estimate, 'A reported fee is a fact, not an estimate.');
    }

    public function test_an_estimate_survives_when_the_event_carries_no_fee(): void
    {
        $payment = $this->pendingPayment();
        $estimate = $payment->processing_fee;

        (new ProcessPayMongoWebhook($this->event($payment->intent_id)))
            ->handle(app(PayMongo::class));

        $fresh = $payment->fresh();

        $this->assertSame('paid', $fresh->status);
        $this->assertSame($estimate, $fresh->processing_fee, 'No number means no change.');
        $this->assertTrue($fresh->fee_is_estimate, 'The row must keep saying the fee is unverified.');
    }

    public function test_commission_is_recognised_only_on_collected_jobs(): void
    {
        $earned = (int) Payment::moved()->onCollectedJobs()->sum('commission');
        $captured = (int) Payment::moved()->sum('commission');

        // Without money captured on unfinished jobs this test proves nothing.
        $this->assertLessThan($captured, $earned, 'The seed must contain commission on jobs that are not collected.');

        $unfinished = Payment::moved()
            ->whereHas('job', fn ($job) => $job->where('status', '!=', 'collected'))
            ->firstOrFail();

        $this->assertFalse(
            Payment::moved()->onCollectedJobs()->whereKey($unfinished->id)->exists(),
            'Commission on a job still on the press is not revenue yet.',
        );

        // Collecting the job is what turns it into revenue.
        $job = $unfinished->job;
        $job->update(['status' => 'collected']);

        $this->assertTrue(Payment::moved()->onCollectedJobs()->whereKey($unfinished->id)->exists());
    }

    public function test_a_refund_after_release_leaves_a_debt_the_ledger_can_show(): void
    {
        $payment = Payment::where('status', 'paid')
            ->where('payout_status', 'paid')
            ->whereColumn('refunded_amount', '<', 'amount')
            ->firstOrFail();

        $this->assertSame(0, $payment->debtToPlatform(), 'Nothing is owed before a refund.');

        app(Refund::class)->refundInFull($payment);

        $fresh = $payment->fresh();

        // The shop already has the net. The customer is paid from the platform's
        // own balance, so the fee is a real loss and has to be nameable.
        $this->assertGreaterThan(0, $fresh->debtToPlatform());
        $this->assertSame($fresh->processing_fee, $fresh->debtToPlatform());
        $this->assertTrue(Payment::refundedAfterRelease()->whereKey($fresh->id)->exists());
    }

    public function test_a_refund_before_release_owes_nothing(): void
    {
        $payment = Payment::where('status', 'paid')
            ->where('payout_status', 'pending')
            ->whereColumn('refunded_amount', '<', 'amount')
            ->firstOrFail();

        app(Refund::class)->refundInFull($payment);

        // The money was still in the platform's hands, so nothing is out of
        // pocket — the negative net comes off the release that never happened.
        $this->assertSame(0, $payment->fresh()->debtToPlatform());
    }
}
