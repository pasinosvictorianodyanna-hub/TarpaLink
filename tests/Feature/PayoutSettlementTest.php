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

    private function pending(): Payment
    {
        return Payment::awaitingPayout()->firstOrFail();
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
        $payment = $this->pending();

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
        $payment = $this->pending();
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
        $payment = $this->pending();

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

        $pending = Payment::awaitingPayout()->take(3)->get();
        $alreadyPaid = Payment::where('status', 'paid')->where('payout_status', 'paid')->firstOrFail();
        $originalDate = $alreadyPaid->payout_date?->toDateString();

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callTableBulkAction('settleSelected', $pending->pluck('id')->push($alreadyPaid->id)->all());

        foreach ($pending as $payment) {
            $this->assertSame('paid', $payment->fresh()->payout_status);
        }

        // An already-released row keeps its original date.
        $this->assertSame($originalDate, $alreadyPaid->fresh()->payout_date?->toDateString());
    }

    public function test_a_shop_owner_cannot_settle_their_own_payout(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $payment = $this->pending();

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
