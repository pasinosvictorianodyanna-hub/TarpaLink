<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cancellation;
use App\Services\Refund;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #82.
 *
 * `payments.status` carried a `refunded` value from #11 and nothing ever wrote
 * it, so a job the shop could not produce had no way out of the ledger — while
 * the published policy promised "a full refund any time before you approve the
 * proof". These tests pin the arithmetic that decision produces: the commission
 * comes back, the processor's fee does not, and the shop carries it.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    /** A settled payment: ₱1,000 gross, ₱50 commission, ₱40 processor fee. */
    private function settled(?Job $job = null, int $amount = 100_000, int $commission = 5_000, int $fee = 4_000): Payment
    {
        $job ??= $this->inkwell()->jobs()->firstOrFail();

        return Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_'.uniqid(),
            'amount' => $amount,
            'commission' => $commission,
            'processing_fee' => $fee,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function test_a_full_refund_leaves_the_shop_carrying_the_processor_fee(): void
    {
        $payment = $this->settled();

        // Sanity: before the refund the shop is owed ₱1,000 − ₱50 − ₱40.
        $this->assertSame(91_000, $payment->net());

        $refunded = app(Refund::class)->refundInFull($payment);

        $this->assertSame('refunded', $refunded->status);
        $this->assertSame(100_000, $refunded->refunded_amount);
        $this->assertSame(5_000, $refunded->commission_refunded);
        $this->assertTrue($refunded->isFullyRefunded());

        // Not zero: the customer got everything back, we kept no commission, and
        // the processor kept its ₱40. The shop bears it — the decision on #81.
        $this->assertSame(-4_000, $refunded->net());
        // Short form: `signed()` prints whole pesos without the trailing .00.
        $this->assertSame('−₱40', Money::signed($refunded->net()));
    }

    public function test_a_partial_refund_reverses_the_commission_in_proportion(): void
    {
        $payment = $this->settled();

        $refunded = app(Refund::class)->refund($payment, 40_000);

        // 40% back, so 40% of the ₱50 commission comes off with it.
        $this->assertSame(2_000, $refunded->commission_refunded);

        // (1,000 − 400) − (50 − 20) − 40 = 530.
        $this->assertSame(53_000, $refunded->net());

        // Still a settled payment — the job was not cancelled, part of it was.
        $this->assertSame('paid', $refunded->status);
        $this->assertFalse($refunded->isFullyRefunded());
    }

    public function test_repeated_partial_refunds_add_up_and_stop_at_the_gross(): void
    {
        $payment = $this->settled();
        $service = app(Refund::class);

        $service->refund($payment, 60_000);
        $payment->refresh();

        // The second refund is measured against what is left, not the original.
        $this->assertSame(40_000, $payment->remainingRefundable());

        $service->refund($payment, 40_000);
        $payment->refresh();

        $this->assertSame(100_000, $payment->refunded_amount);
        $this->assertSame(5_000, $payment->commission_refunded);
        $this->assertSame('refunded', $payment->status);

        // Nothing left, so a third is refused rather than overdrawing the row.
        $this->expectException(ValidationException::class);
        $service->refund($payment, 1);
    }

    public function test_rounding_never_reverses_more_commission_than_was_charged(): void
    {
        // ₱1,000 at a rate that does not divide evenly: three thirds of the
        // commission is 999.99 centavos of a 1,000, and naive per-refund rounding
        // would reverse 1,002.
        $payment = $this->settled(amount: 100_000, commission: 1_000, fee: 4_000);
        $service = app(Refund::class);

        foreach ([33_333, 33_333, 33_334] as $slice) {
            $service->refund($payment, $slice);
            $payment->refresh();
        }

        $this->assertSame(1_000, $payment->commission_refunded);
        $this->assertSame('refunded', $payment->status);
    }

    public function test_a_payment_that_was_never_settled_cannot_be_refunded(): void
    {
        $job = $this->inkwell()->jobs()->firstOrFail();

        $pending = Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_pending_refund',
            'amount' => 100_000,
            'commission' => 5_000,
            'processing_fee' => 4_000,
            'status' => 'pending',
        ]);

        $this->expectException(ValidationException::class);
        app(Refund::class)->refund($pending, 1_000);
    }

    public function test_a_refund_is_written_onto_the_jobs_history(): void
    {
        $payment = $this->settled();
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        app(Refund::class)->refund($payment, 25_000, $admin, 'Customer changed the size');

        $note = $payment->job->activities()
            ->where('kind', JobActivity::NOTE)
            ->where('note', 'like', 'Refunded%')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $note->user_id);
        $this->assertSame('Refunded ₱250.00 of ₱1,000.00', $note->note);
        $this->assertSame('Customer changed the size', $note->body);
        // A refund is not a status change, so it must not claim to be one.
        $this->assertNull($note->from_status);
        $this->assertNull($note->to_status);
    }

    public function test_a_customer_cancelling_before_the_proof_is_approved_gets_their_money_back(): void
    {
        $job = Job::where('status', 'quoted')->with(['user', 'payment'])->firstOrFail();

        // A job has exactly one payment, so this settles the quote it already
        // carries rather than adding a second row beside it.
        $payment = $job->payment;
        $payment->forceFill([
            'amount' => 70_200,
            'commission' => 3_510,
            'processing_fee' => 3_255,
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $this->actingAs($job->user)
            // Jobs bind by `reference`, not id — `route()` resolves that.
            ->post(route('jobs.cancel', $job), ['reason' => 'Ordered the wrong size'])
            ->assertRedirect(route('jobs.show', $job));

        $job->refresh();
        $payment->refresh();

        $this->assertSame('cancelled', $job->status);
        $this->assertSame('refunded', $payment->status);
        $this->assertSame(70_200, $payment->refunded_amount);
        $this->assertSame(-3_255, $payment->net());
    }

    public function test_a_customer_cannot_cancel_a_job_that_has_gone_to_press(): void
    {
        $job = Job::where('status', 'approved')->firstOrFail();

        $this->actingAs($job->user)
            ->post(route('jobs.cancel', $job))
            ->assertForbidden();

        $this->assertSame('approved', $job->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_someone_elses_job(): void
    {
        $job = Job::where('status', 'quoted')->firstOrFail();
        $stranger = User::where('email', 'marisol@example.com')->firstOrFail();

        $this->assertNotSame($stranger->id, $job->user_id);

        // 404, not 403: the reference is guessable, so the answer must not
        // confirm that the job exists. Same choice the show route makes.
        $this->actingAs($stranger)
            ->post(route('jobs.cancel', $job))
            ->assertNotFound();

        $this->assertSame('quoted', $job->fresh()->status);
    }

    public function test_the_shop_cancelling_after_approval_returns_the_money(): void
    {
        $job = Job::where('status', 'approved')->where('shop_id', $this->inkwell()->id)->firstOrFail();

        // One payment per job is the model's assumption (`Job::payment()` is a
        // hasOne), so the seeded row goes first. Without this the payment this
        // test creates is the *second* on the job, `$job->payment` resolves to
        // the other one, and the assertions below measure a row nothing touched
        // — which is how the previous version passed while asserting the
        // opposite of what the code did.
        $job->payment()->delete();

        $payment = $this->settled($job);

        $this->actingAs($this->owner())
            ->post(route('console.jobs.status', $job), ['status' => 'cancelled'])
            ->assertRedirect(route('console.jobs.show', $job));

        $this->assertSame('cancelled', $job->fresh()->status);

        // FR-105 still stops the *customer* walking away after approval — that
        // is a rule about who decides. It was never a licence to keep their
        // money: a job that will not be printed cannot leave the platform
        // holding the payment, so whoever cancels, the remainder goes back.
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(100_000, $payment->fresh()->refunded_amount);

        // The processor keeps its fee, so a full refund still nets the shop
        // negative — the same arithmetic as #82.
        $this->assertSame(-4_000, $payment->fresh()->net());
    }

    public function test_cancelling_an_unpaid_job_moves_no_money(): void
    {
        $job = Job::where('status', 'awaiting_proof')->doesntHave('payment')->firstOrFail();

        $refunded = app(Cancellation::class)->cancel($job);

        $this->assertNull($refunded);
        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_the_reports_page_shows_the_refund_and_the_negative_net(): void
    {
        $payment = $this->settled();
        app(Refund::class)->refundInFull($payment);

        $response = $this->actingAs($this->owner())->get('/console/reports')->assertOk();

        $response->assertSee('Refunded');
        $response->assertSee('−₱40');

        // The row stays in the ledger: dropping it would hide the loss instead of
        // netting it off the next payout.
        $totals = $response->viewData('totals');
        $this->assertGreaterThanOrEqual(100_000, $totals['refunded']);
    }

    public function test_the_admin_payouts_screen_still_lists_a_refunded_payment(): void
    {
        $payment = $this->settled();
        app(Refund::class)->refundInFull($payment);

        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        // A refunded payment owes the shop nothing, but an admin who cannot see
        // it cannot explain the short payout either.
        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->assertCanSeeTableRecords([$payment->fresh()]);
    }
}
