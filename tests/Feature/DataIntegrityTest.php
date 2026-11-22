<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\Payment;
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
 * These are the rules from docs/CONTRACT.md expressed as assertions, so a change
 * that quietly breaks the ledger fails here instead of in production.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Every captured payment reconciles: gross = commission + processor fee +
     * what the shop is actually paid. The fee is part of the split because the
     * shop bears it — see #81.
     */
    public function test_every_captured_payment_splits_into_commission_fee_and_net(): void
    {
        foreach (Payment::where('status', 'paid')->get() as $payment) {
            $this->assertSame(
                $payment->amount - $payment->commission - $payment->processing_fee,
                $payment->net(),
                "Payment {$payment->id}: net does not equal amount - commission - fee.",
            );

            $this->assertSame(
                $payment->amount,
                $payment->commission + $payment->processing_fee + $payment->net(),
                "Payment {$payment->id}: the split does not add back up to the gross.",
            );
        }
    }

    public function test_no_money_is_negative(): void
    {
        $this->assertSame(0, Payment::where('amount', '<', 0)->count(), 'Negative payment.');
        $this->assertSame(0, Payment::where('commission', '<', 0)->count(), 'Negative commission.');
        $this->assertSame(0, Job::where('total', '<', 0)->count(), 'Negative job total.');
        $this->assertSame(0, PriceTier::where('unit_price', '<', 0)->count(), 'Negative tier price.');
    }

    public function test_a_payment_is_never_paid_out_before_it_is_paid(): void
    {
        $this->assertSame(
            0,
            Payment::where('payout_status', 'paid')->where('status', '!=', 'paid')->count(),
            'A payment was marked paid out while still unpaid.',
        );
    }

    public function test_a_payout_status_is_one_of_the_two_states(): void
    {
        $this->assertSame(
            0,
            Payment::whereNotIn('payout_status', ['pending', 'paid'])->count(),
            'Unknown payout_status value.',
        );
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
        $rates = Plan::pluck('commission_rate', 'code');

        foreach (Shop::all() as $shop) {
            $this->assertArrayHasKey($shop->plan, $rates->all(), "Shop {$shop->name} is on unknown plan {$shop->plan}.");
            $this->assertSame(
                $rates[$shop->plan],
                $shop->commission_rate,
                "Shop {$shop->name} carries rate {$shop->commission_rate} but plan {$shop->plan} is {$rates[$shop->plan]}.",
            );
        }
    }

    public function test_a_captured_payment_was_commissioned_at_its_shops_rate(): void
    {
        foreach (Payment::with('job.shop')->where('status', 'paid')->get() as $payment) {
            $shop = $payment->job?->shop;

            $this->assertNotNull($shop, "Payment {$payment->id} has no shop through its job.");

            $this->assertSame(
                (int) round($payment->amount * $shop->commission_rate / 10000),
                $payment->commission,
                "Payment {$payment->id}: commission does not match {$shop->name}'s rate.",
            );
        }
    }

    public function test_a_shop_never_has_more_than_one_active_subscription(): void
    {
        // Subscriptions are created on upgrade, not seeded, so zero is normal.
        // Two active rows would mean a shop is being billed twice.
        foreach (Shop::all() as $shop) {
            $active = Subscription::where('shop_id', $shop->id)->where('status', 'active')->count();

            $this->assertLessThanOrEqual(1, $active, "Shop {$shop->name} has {$active} active subscriptions.");
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
            $this->assertGreaterThanOrEqual(0, $tier->min_width, "Tier {$tier->id} has a negative minimum.");

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
