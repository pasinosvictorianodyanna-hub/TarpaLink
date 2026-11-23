<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\Shops\Pages\EditShop;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\Billing as BillingService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The platform has two revenue pots: commission on a job, and the monthly
 * subscription. Only the first was ever counted, and only the first was ever
 * certain to be collected.
 *
 * `shops.plan` is a denormalised label, and two writers — the admin form and
 * this project's own seeder — set it without creating a `subscriptions` row.
 * `issueDueInvoices()` walks subscriptions, so those shops were never billed:
 * they kept the discounted commission rate and paid no fee. The daily command
 * then reported a clean run, because an empty result was read as "everything is
 * already billed" when it also meant "there is nothing to bill".
 *
 * These are the acceptance criteria for the fix, in the order the money moves.
 */
class SubscriptionRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_every_active_shop_on_a_paid_plan_is_subscribed(): void
    {
        $paid = Plan::where('price', '>', 0)->pluck('code');

        $this->assertGreaterThan(0, $paid->count(), 'The plan catalogue must have a paid tier to test against.');

        $unbilled = Shop::where('status', 'active')
            ->whereIn('plan', $paid)
            ->whereDoesntHave('subscriptions', fn ($query) => $query->where('status', 'active'))
            ->pluck('name');

        $this->assertTrue(
            $unbilled->isEmpty(),
            'Paid-plan shops with no subscription, so nothing will ever bill them: '.$unbilled->implode(', '),
        );
    }

    public function test_a_free_plan_shop_carries_no_subscription(): void
    {
        $starter = Shop::where('plan', 'starter')->where('status', 'active')->firstOrFail();

        $this->assertSame(
            0,
            $starter->subscriptions()->where('status', 'active')->count(),
            'A free plan has no fee, so it needs no subscription row.',
        );
    }

    public function test_the_seed_produces_bills_and_one_of_them_is_collected(): void
    {
        $this->assertGreaterThan(0, Invoice::count(), 'Seeding should produce subscription invoices.');
        $this->assertGreaterThan(
            0,
            Invoice::where('status', Invoice::PAID)->count(),
            'At least one bill should be collected, or the revenue tile reads zero on a healthy database.',
        );
        $this->assertGreaterThan(0, Invoice::unpaid()->count(), 'At least one bill should still be open.');
    }

    public function test_the_command_names_a_paid_plan_shop_it_cannot_bill(): void
    {
        // The exact shape that leaked: the label written, the bill never made.
        $shop = Shop::where('plan', 'starter')->where('status', 'active')->firstOrFail();
        $shop->forceFill(['plan' => 'pro', 'commission_rate' => 150])->save();

        $this->assertTrue(
            app(BillingService::class)->unbilledShops()->contains($shop),
            'A paid plan with no subscription must be visible to the biller.',
        );

        $this->artisan('billing:issue')
            ->expectsOutputToContain('no active subscription')
            ->assertSuccessful();
    }

    public function test_saving_a_plan_change_in_the_admin_panel_creates_the_bill(): void
    {
        Filament::setCurrentPanel('admin');

        $shop = Shop::where('plan', 'starter')->where('status', 'active')->firstOrFail();
        $shop->subscriptions()->update(['status' => 'cancelled', 'ends_at' => now()]);

        $before = Invoice::where('shop_id', $shop->id)->count();

        Livewire::actingAs($this->admin())
            ->test(EditShop::class, ['record' => $shop->getRouteKey()])
            ->fillForm(['plan' => 'pro'])
            ->call('save');

        $shop->refresh();

        $this->assertSame('pro', $shop->plan);
        $this->assertSame(150, $shop->commission_rate, 'The rate follows the plan.');

        $subscription = $shop->subscriptions()->where('status', 'active')->first();

        $this->assertNotNull($subscription, 'A plan change must create a subscription.');
        $this->assertSame(Plan::where('code', 'pro')->value('id'), $subscription->plan_id);

        $this->assertSame($before + 1, Invoice::where('shop_id', $shop->id)->count());
        $this->assertSame(
            149_900,
            (int) Invoice::where('shop_id', $shop->id)->unpaid()->value('amount'),
            'The new plan must be billed at its own price.',
        );
    }

    public function test_the_admin_dashboard_counts_subscription_revenue(): void
    {
        Filament::setCurrentPanel('admin');

        // The overview used to show only commission, so a platform could bill
        // nothing for a year and the screen would look unchanged. The second pot
        // has to be on it, and it has to carry a number.
        Livewire::actingAs($this->admin())
            ->test(Dashboard::class)
            ->assertSee('Subscription revenue')
            ->assertSee('active paid subscription');
    }

    public function test_an_edit_that_does_not_touch_the_plan_bills_nothing(): void
    {
        Filament::setCurrentPanel('admin');

        $shop = Shop::where('plan', 'growth')->where('status', 'active')->firstOrFail();
        $before = Invoice::where('shop_id', $shop->id)->count();

        Livewire::actingAs($this->admin())
            ->test(EditShop::class, ['record' => $shop->getRouteKey()])
            ->fillForm(['city' => 'Pasay'])
            ->call('save');

        $this->assertSame(
            $before,
            Invoice::where('shop_id', $shop->id)->count(),
            'Correcting an address must not issue a bill.',
        );
    }
}
