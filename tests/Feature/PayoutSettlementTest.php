<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Releasing money to shops. Until this existed, `payout_status` could only ever
 * be whatever the seeder wrote — shops earned and were never paid.
 */
class PayoutSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    /**
     * Captured, unreleased, and for a job that has actually been collected.
     *
     * The seeder carries payments for jobs in every state, so "pending payout"
     * and "releasable" are no longer the same set — see #94.
     */
    private function releasable(): Payment
    {
        return Payment::awaitingPayout()
            ->whereHas('job', fn ($q) => $q->where('status', 'collected'))
            ->firstOrFail();
    }

    /** Captured and unreleased, but the job has not reached collection. */
    private function notYetCollected(): Payment
    {
        return Payment::awaitingPayout()
            ->whereHas('job', fn ($q) => $q->whereNotIn('status', ['collected', 'cancelled']))
            ->firstOrFail();
    }

    public function test_only_an_administrator_can_open_the_payouts_screen(): void
    {
        $this->actingAs($this->admin())->get('/admin/payouts')->assertOk();

        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/admin/payouts')
            ->assertForbidden();

        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/admin/payouts')
            ->assertForbidden();
    }

    public function test_an_administrator_can_release_a_pending_payout(): void
    {
        $admin = $this->admin();
        $payment = $this->releasable();

        $this->assertSame('pending', $payment->payout_status);
        $this->assertNull($payment->payout_date);

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callAction(TestAction::make('settle')->table($payment));

        $payment->refresh();

        $this->assertSame('paid', $payment->payout_status);
        $this->assertSame(now()->toDateString(), $payment->payout_date->toDateString());
    }

    public function test_settling_never_changes_the_money(): void
    {
        $payment = $this->releasable();
        $before = [$payment->amount, $payment->commission, $payment->net(), $payment->status];

        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->callAction(TestAction::make('settle')->table($payment));

        $after = $payment->fresh();

        $this->assertSame($before, [$after->amount, $after->commission, $after->net(), $after->status]);
    }

    public function test_an_already_paid_payout_is_not_offered_for_release(): void
    {
        $admin = $this->admin();
        $payment = $this->releasable();

        // Release it, then backdate so a second release would be visible.
        $payment->update(['payout_status' => 'paid', 'payout_date' => '2026-01-15']);

        // The screen defaults to pending, so a settled row is not listed at all —
        // there is no button to press twice.
        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->assertCanNotSeeTableRecords([$payment]);

        $this->assertSame('2026-01-15', $payment->fresh()->payout_date->toDateString());
    }

    public function test_the_bulk_action_releases_only_the_pending_rows(): void
    {
        $admin = $this->admin();

        $releasable = Payment::awaitingPayout()
            ->whereHas('job', fn ($q) => $q->where('status', 'collected'))
            ->take(3)
            ->get();
        $alreadyPaid = Payment::where('status', 'paid')->where('payout_status', 'paid')->firstOrFail();
        $originalDate = $alreadyPaid->payout_date?->toDateString();

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callTableBulkAction('settleSelected', $releasable->pluck('id')->push($alreadyPaid->id)->all());

        foreach ($releasable as $payment) {
            $this->assertSame('paid', $payment->fresh()->payout_status);
        }

        // An already-released row keeps its original date.
        $this->assertSame($originalDate, $alreadyPaid->fresh()->payout_date?->toDateString());
    }

    public function test_a_payout_is_not_released_before_the_job_is_collected(): void
    {
        $payment = $this->notYetCollected();

        $this->assertFalse($payment->mayBeReleased());
        $this->assertStringContainsString('collected', $payment->releaseBlockedReason());

        // The button is there but greyed out: hiding it would make a blocked row
        // look like a row that has already been dealt with.
        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->assertActionDisabled(TestAction::make('settle')->table($payment))
            // And the badge says so, instead of calling it "Pending" alongside
            // money that really is payable.
            ->assertSee('Awaiting collection');

        $this->assertSame('pending', $payment->fresh()->payout_status);
        $this->assertNull($payment->fresh()->payout_date);
    }

    public function test_a_collected_job_is_released_with_the_button_enabled(): void
    {
        $payment = $this->releasable();

        $this->assertTrue($payment->mayBeReleased());
        $this->assertNull($payment->releaseBlockedReason());

        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->assertActionEnabled(TestAction::make('settle')->table($payment));
    }

    public function test_a_cancelled_job_is_never_paid_out(): void
    {
        $payment = $this->releasable();

        // Status is flipped directly rather than through `Cancellation`, so this
        // exercises the release gate and not the refund. Since #108 cancelling
        // from `approved` returns the money, but the gate must hold even for a
        // payment that was never refunded — a bulk release is the wrong place to
        // discover that.
        $payment->job->update(['status' => 'cancelled']);

        $payment->refresh();

        $this->assertFalse($payment->mayBeReleased());
        $this->assertSame(
            'The job was cancelled, so there is nothing to pay for.',
            $payment->releaseBlockedReason(),
        );

        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->assertActionDisabled(TestAction::make('settle')->table($payment));
    }

    public function test_the_bulk_action_leaves_uncollected_rows_alone(): void
    {
        $notYet = $this->notYetCollected();
        $ready = $this->releasable();

        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->callTableBulkAction('settleSelected', [$notYet->id, $ready->id]);

        $this->assertSame('pending', $notYet->fresh()->payout_status);
        $this->assertNull($notYet->fresh()->payout_date);
        $this->assertSame('paid', $ready->fresh()->payout_status);
    }

    public function test_a_shop_owner_cannot_settle_their_own_payout(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $payment = $this->releasable();

        $this->assertFalse($owner->can('settle', $payment));
        $this->assertTrue($this->admin()->can('settle', $payment));
    }

    public function test_the_screen_lists_captured_money_only(): void
    {
        // A pending intent is not revenue and must not appear as owed.
        $unpaid = Payment::where('status', '!=', 'paid')->first();

        if ($unpaid) {
            Livewire::actingAs($this->admin())
                ->test(ListPayouts::class)
                ->assertCanNotSeeTableRecords([$unpaid]);
        }

        $paid = Payment::where('status', 'paid')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(ListPayouts::class)
            ->assertCanSeeTableRecords([$paid]);
    }
}
