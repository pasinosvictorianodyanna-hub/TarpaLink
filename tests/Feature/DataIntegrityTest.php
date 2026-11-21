<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\PriceTier;
use App\Models\Proof;
use App\Models\QuantityDiscount;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariants the money and catalogue have to hold, checked against seeded data.
 *
 * These are not "does the seeder run" tests — they are the rules from
 * docs/CONTRACT.md expressed as assertions, so a future change that quietly
 * breaks the ledger fails here instead of in production.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_every_payout_satisfies_gross_minus_commission_equals_net(): void
    {
        $broken = Payout::whereRaw('gross - commission <> net')->get(['id', 'gross', 'commission', 'net']);

        $this->assertTrue($broken->isEmpty(), 'Rows that do not add up: '.$broken->toJson());
    }

    public function test_no_money_is_negative_or_null(): void
    {
        $this->assertSame(0, Payout::where('gross', '<', 0)->count(), 'Negative payout gross.');
        $this->assertSame(0, Payout::where('net', '<', 0)->count(), 'Negative payout net.');
        $this->assertSame(0, Payment::where('amount', '<', 0)->count(), 'Negative payment.');
        $this->assertSame(0, Job::where('total', '<', 0)->count(), 'Negative job total.');
        $this->assertSame(0, PriceTier::where('unit_price', '<', 0)->count(), 'Negative tier price.');
    }

    public function test_every_captured_payment_has_exactly_one_ledger_row(): void
    {
        $missing = Payment::where('status', 'paid')->whereDoesntHave('payout')->pluck('id');

        $this->assertTrue($missing->isEmpty(), 'Paid payments with no payout row: '.$missing->implode(','));

        $orphans = Payout::whereDoesntHave('payment')->pluck('id');
        $this->assertTrue($orphans->isEmpty(), 'Payouts with no payment: '.$orphans->implode(','));
    }

    public function test_the_ledger_gross_matches_the_payment_amount(): void
    {
        $mismatched = Payout::with('payment')
            ->get()
            ->filter(fn (Payout $p) => $p->payment && $p->gross !== $p->payment->amount)
            ->pluck('id');

        $this->assertTrue($mismatched->isEmpty(), 'Payout gross != payment amount: '.$mismatched->implode(','));
    }

    public function test_a_job_subtotal_is_the_sum_of_its_lines(): void
    {
        foreach (Job::with('lines')->get() as $job) {
            $sum = (int) $job->lines->sum('amount');

            $this->assertSame(
                $job->subtotal,
                $sum,
                "Job {$job->reference}: subtotal {$job->subtotal} but lines sum to {$sum}.",
            );
        }
    }

    public function test_a_job_total_is_subtotal_minus_discount(): void
    {
        foreach (Job::all() as $job) {
            $this->assertSame(
                $job->subtotal - $job->discount,
                $job->total,
                "Job {$job->reference}: total does not equal subtotal - discount.",
            );
        }
    }

    public function test_every_job_has_at_least_one_line(): void
    {
        $empty = Job::whereDoesntHave('lines')->pluck('reference');

        $this->assertTrue($empty->isEmpty(), 'Jobs with no priced lines: '.$empty->implode(','));
    }

    public function test_a_shops_rate_matches_the_plan_it_is_on(): void
    {
        $rates = Plan::pluck('commission_rate', 'key');

        foreach (Shop::all() as $shop) {
            $this->assertArrayHasKey($shop->plan, $rates->all(), "Shop {$shop->name} is on unknown plan {$shop->plan}.");
            $this->assertSame(
                $rates[$shop->plan],
                $shop->commission_rate,
                "Shop {$shop->name} carries rate {$shop->commission_rate} but plan {$shop->plan} is {$rates[$shop->plan]}.",
            );
        }
    }

    public function test_every_active_shop_has_exactly_one_active_subscription(): void
    {
        foreach (Shop::where('status', 'active')->get() as $shop) {
            $active = Subscription::where('shop_id', $shop->id)->where('status', 'active')->count();

            $this->assertSame(1, $active, "Shop {$shop->name} has {$active} active subscriptions.");
        }
    }

    public function test_every_paid_invoice_has_a_paid_at_timestamp(): void
    {
        $bad = Invoice::where('status', 'paid')->whereNull('paid_at')->pluck('number');

        $this->assertTrue($bad->isEmpty(), 'Paid invoices with no paid_at: '.$bad->implode(','));
    }

    public function test_an_approved_proof_has_an_approval_timestamp(): void
    {
        $bad = Proof::where('status', 'approved')->whereNull('approved_at')->pluck('id');

        $this->assertTrue($bad->isEmpty(), 'Approved proofs with no approved_at: '.$bad->implode(','));
    }

    public function test_price_tiers_have_a_sane_range(): void
    {
        foreach (PriceTier::all() as $tier) {
            $this->assertGreaterThanOrEqual(
                0,
                $tier->min_width,
                "Tier {$tier->id} has a negative minimum.",
            );

            if ($tier->max_width !== null) {
                $this->assertGreaterThan(
                    $tier->min_width,
                    $tier->max_width,
                    "Tier {$tier->id} ({$tier->label}) has max <= min, so no size can ever match it.",
                );
            }
        }
    }

    public function test_quantity_discounts_are_percentages(): void
    {
        foreach (QuantityDiscount::all() as $discount) {
            $this->assertGreaterThan(0, $discount->min_qty, "Discount {$discount->id} has min_qty <= 0.");
            $this->assertGreaterThanOrEqual(1, $discount->percent, "Discount {$discount->id} is below 1%.");
            $this->assertLessThanOrEqual(100, $discount->percent, "Discount {$discount->id} exceeds 100%.");
        }
    }

    public function test_every_service_belongs_to_a_shop_and_has_a_price(): void
    {
        foreach (Service::with('shop')->get() as $service) {
            $this->assertNotNull($service->shop, "Service {$service->id} has no shop.");
            $this->assertGreaterThan(0, $service->tiers()->count(), "Service {$service->name} has no price tiers.");
        }
    }

    public function test_jobs_reference_a_service_that_belongs_to_the_same_shop(): void
    {
        foreach (Job::with('service')->get() as $job) {
            $this->assertNotNull($job->service, "Job {$job->reference} has no service.");

            $this->assertSame(
                $job->shop_id,
                $job->service->shop_id,
                "Job {$job->reference} is with shop {$job->shop_id} but prices a service from shop {$job->service->shop_id}.",
            );
        }
    }
}
