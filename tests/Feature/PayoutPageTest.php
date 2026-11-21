<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Shop\Pages\Payouts as PayoutsPage;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ISSUES.md #7: the month total on the ledger must match a manual SUM() on
 * payments, and a payout has to be movable to "paid".
 */
class PayoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    public function test_the_month_total_matches_a_manual_sum_on_payments(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $expectedGross = (int) Payment::query()
            ->whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $expectedCommission = (int) Payment::query()
            ->whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('commission');

        $expectedNet = $expectedGross - $expectedCommission;

        Livewire::actingAs($this->owner())
            ->test(PayoutsPage::class)
            ->assertSet('monthGross', $expectedGross)
            ->assertSet('monthCommission', $expectedCommission)
            ->assertSet('monthNet', $expectedNet)
            ->assertSee(Money::format($expectedNet));
    }

    public function test_the_ledger_agrees_with_payments_for_the_same_month(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $paymentsThisMonth = (int) Payment::query()
            ->whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $ledgerThisMonth = (int) Payout::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('gross');

        $this->assertSame($paymentsThisMonth, $ledgerThisMonth);
    }

    public function test_awaiting_payout_excludes_rows_already_paid(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $expected = (int) $shop->payouts()
            ->whereIn('status', ['pending', 'scheduled'])
            ->sum('net');

        Livewire::actingAs($this->owner())
            ->test(PayoutsPage::class)
            ->assertSet('pendingNet', $expected);
    }

    public function test_an_administrator_can_mark_a_payout_paid(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
        $payout = Payout::where('status', '!=', 'paid')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callAction(TestAction::make('markPaid')->table($payout));

        $payout->refresh();

        $this->assertSame('paid', $payout->status);
        $this->assertNotNull($payout->payout_date);
        $this->assertSame(now()->toDateString(), $payout->payout_date->toDateString());
    }

    public function test_a_payout_can_be_scheduled_before_it_is_paid(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
        $payout = Payout::where('status', 'pending')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callAction(TestAction::make('schedule')->table($payout));

        $payout->refresh();

        $this->assertSame('scheduled', $payout->status);
        $this->assertNotNull($payout->payout_date);
    }

    public function test_the_ledger_rows_are_never_edited_by_the_payout_actions(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
        $payout = Payout::where('status', 'pending')->firstOrFail();
        $before = [$payout->gross, $payout->commission, $payout->net, $payout->commission_rate];

        Livewire::actingAs($admin)
            ->test(ListPayouts::class)
            ->callAction(TestAction::make('markPaid')->table($payout));

        $after = $payout->fresh();

        $this->assertSame($before, [$after->gross, $after->commission, $after->net, $after->commission_rate]);
    }
}
