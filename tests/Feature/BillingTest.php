<?php

namespace Tests\Feature;

use App\Filament\Shop\Pages\Billing as BillingPage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\Billing;
use App\Services\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ISSUES.md #12: plans, the crossover maths, and the promise that an upgrade
 * only affects the *next* payout.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function plan(string $key): Plan
    {
        return Plan::where('key', $key)->firstOrFail();
    }

    public function test_the_documented_crossover_points_are_what_the_plans_compute(): void
    {
        // Growth beats Starter above ₱29,950 monthly GMV.
        $this->assertSame(2_995_000, $this->plan('growth')->crossoverAgainst($this->plan('starter')));

        // Pro beats Growth above ₱60,000.
        $this->assertSame(6_000_000, $this->plan('pro')->crossoverAgainst($this->plan('growth')));
    }

    public function test_cost_at_crossover_is_identical_for_both_plans(): void
    {
        $growth = $this->plan('growth');
        $starter = $this->plan('starter');
        $gmv = $growth->crossoverAgainst($starter);

        $this->assertSame($starter->monthlyCost($gmv), $growth->monthlyCost($gmv));
    }

    public function test_the_cheapest_plan_flips_at_the_crossover(): void
    {
        $billing = app(Billing::class);

        $this->assertSame('starter', $billing->bestPlanFor(1_000_000)->key);   // ₱10,000
        $this->assertSame('growth', $billing->bestPlanFor(5_000_000)->key);    // ₱50,000
        $this->assertSame('pro', $billing->bestPlanFor(20_000_000)->key);      // ₱200,000
    }

    public function test_upgrading_changes_the_rate_on_the_shop_and_nothing_else(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();  // growth, 3%
        $before = Payment::whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->orderBy('id')->get(['id', 'commission'])
            ->map(fn ($p) => [$p->id, $p->commission])->all();
        $payoutsBefore = Payout::where('shop_id', $shop->id)->orderBy('id')
            ->get(['id', 'gross', 'commission', 'net', 'commission_rate'])
            ->toArray();

        app(Billing::class)->upgrade($shop, $this->plan('pro'));

        $shop->refresh();

        $this->assertSame('pro', $shop->plan);
        $this->assertSame(150, $shop->commission_rate);

        // Every payment row keeps the commission it was captured with.
        $after = Payment::whereIn('id', array_column($before, 0))->orderBy('id')
            ->get(['id', 'commission'])
            ->map(fn ($p) => [$p->id, $p->commission])->all();

        $this->assertSame($before, $after);

        // And so does every ledger row.
        $this->assertSame($payoutsBefore, Payout::where('shop_id', $shop->id)->orderBy('id')
            ->get(['id', 'gross', 'commission', 'net', 'commission_rate'])
            ->toArray());
    }

    public function test_the_next_payout_uses_the_new_rate(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        app(Billing::class)->upgrade($shop, $this->plan('pro'));
        $shop->refresh();

        $job = $shop->jobs()->where('status', 'quoted')->firstOrFail();
        $payment = Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_after_upgrade',
            'amount' => 1_000_000,
            'commission' => 0,
            'status' => 'pending',
        ]);

        app(PaymentSettlement::class)->markPaid($payment);

        $payout = $payment->fresh()->payout;

        $this->assertSame(150, $payout->commission_rate);
        $this->assertSame(15_000, $payout->commission);   // 1.5% of ₱10,000
        $this->assertSame(985_000, $payout->net);
    }

    public function test_upgrading_issues_an_invoice_and_keeps_the_old_ones(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $invoicesBefore = Invoice::where('shop_id', $shop->id)->count();

        app(Billing::class)->upgrade($shop, $this->plan('pro'));

        $this->assertSame($invoicesBefore + 1, Invoice::where('shop_id', $shop->id)->count());
        $this->assertDatabaseHas('invoices', [
            'shop_id' => $shop->id,
            'amount' => 149900,
            'status' => 'open',
        ]);
    }

    public function test_switching_to_the_plan_you_are_already_on_does_nothing(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $subscriptionsBefore = $shop->subscriptions()->count();

        Livewire::actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->test(BillingPage::class)
            ->call('upgrade', 'growth');

        $this->assertSame($subscriptionsBefore, $shop->subscriptions()->count());
    }

    public function test_the_owner_can_upgrade_from_the_billing_page(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        Livewire::actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->test(BillingPage::class)
            ->assertSee('Growth')
            ->call('upgrade', 'pro');

        $this->assertSame('pro', $shop->fresh()->plan);
        $this->assertSame(150, $shop->fresh()->commission_rate);
    }

    public function test_only_the_shops_own_owner_sees_their_invoices(): void
    {
        $inkwell = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $halftone = Shop::where('slug', 'halftone-hub')->firstOrFail();

        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();

        $this->assertTrue($owner->can('viewAny', Invoice::class));
        $this->assertTrue($owner->can('view', Invoice::where('shop_id', $inkwell->id)->firstOrFail()));
        $this->assertFalse($owner->can('view', Invoice::where('shop_id', $halftone->id)->firstOrFail()));
    }
}
