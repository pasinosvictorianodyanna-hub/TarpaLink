<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing as BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
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

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    public function test_crossover_points_match_the_pricing_model(): void
    {
        $growth = Plan::where('code', 'growth')->firstOrFail();
        $pro = Plan::where('code', 'pro')->firstOrFail();

        // Growth beats Starter above ₱29,950 of monthly GMV.
        $this->assertSame(2_995_000, $growth->crossoverFrom($growth->lowerPlan()));

        // Pro beats Growth above ₱60,000.
        $this->assertSame(6_000_000, $pro->crossoverFrom($pro->lowerPlan()));
    }

    public function test_the_cheapest_plan_flips_at_the_crossover_point(): void
    {
        $billing = app(BillingService::class);

        // Just under ₱29,950 → Starter wins.
        $this->assertSame('starter', $billing->bestPlanFor(2_990_000)->code);

        // Just over → Growth wins.
        $this->assertSame('growth', $billing->bestPlanFor(3_000_000)->code);

        // Past ₱60,000 → Pro wins.
        $this->assertSame('pro', $billing->bestPlanFor(6_100_000)->code);
    }

    public function test_switching_plan_changes_the_rate_and_issues_an_invoice(): void
    {
        $shop = $this->inkwell();

        $this->assertSame('growth', $shop->plan);
        $this->assertSame(300, $shop->commission_rate);

        app(BillingService::class)->subscribe($shop, Plan::where('code', 'pro')->firstOrFail());

        $shop->refresh();

        $this->assertSame('pro', $shop->plan);
        $this->assertSame(150, $shop->commission_rate);
        $this->assertSame(1, Subscription::where('shop_id', $shop->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('invoices', ['shop_id' => $shop->id, 'amount' => 149900]);
    }

    /** Issue #12 acceptance: a plan change must not rewrite settled payouts. */
    public function test_historical_payments_are_untouched_by_a_plan_change(): void
    {
        $shop = $this->inkwell();
        $before = Payment::whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $p) => [$p->id, $p->amount, $p->commission])
            ->all();

        $this->assertNotEmpty($before, 'Seed data should include settled payments.');

        app(BillingService::class)->subscribe($shop, Plan::where('code', 'pro')->firstOrFail());

        $after = Payment::whereIn('id', array_column($before, 0))
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $p) => [$p->id, $p->amount, $p->commission])
            ->all();

        $this->assertSame($before, $after);
    }

    public function test_a_free_plan_issues_no_new_bill(): void
    {
        $shop = $this->inkwell();

        app(BillingService::class)->subscribe($shop, Plan::where('code', 'starter')->firstOrFail());

        // No *open* bill. A shop that has been on a paid plan keeps its history
        // — the invoice it already settled does not vanish because it downgraded
        // — so "no invoice at all" was only ever true of a shop that had never
        // been billed.
        $this->assertSame(0, Invoice::where('shop_id', $shop->id)->unpaid()->count());
    }

    /**
     * The page has to show the money, not just the plan names: a plan card with
     * no price on it is a card an owner cannot choose from.
     */
    public function test_the_billing_page_renders_the_prices_and_can_switch_plans(): void
    {
        $growth = Plan::where('code', 'growth')->firstOrFail();
        $pro = Plan::where('code', 'pro')->firstOrFail();

        $this->actingAs($this->owner())
            ->get('/console/billing')
            ->assertOk()
            ->assertSee('Growth')
            ->assertSee(\App\Support\Money::format($growth->price));

        $this->actingAs($this->owner())
            ->post('/console/billing', ['code' => $pro->code])
            ->assertRedirect();

        $this->assertSame('pro', $this->inkwell()->fresh()->plan);
    }
}
