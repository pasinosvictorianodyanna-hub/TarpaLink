<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plans, upgrades and invoices.
 *
 * The one rule that matters: an upgrade changes the rate applied to the *next*
 * payout. It never touches a payment or payout row that already exists — those
 * carry their own snapshot. See docs/ISSUES.md #12.
 */
class Billing
{
    /** @return Collection<int, Plan> */
    public function plans(): Collection
    {
        return Plan::orderBy('sort')->get();
    }

    public function planFor(Shop $shop): ?Plan
    {
        return Plan::where('key', $shop->plan)->first();
    }

    public function monthlyCost(Plan $plan, int $gmvCentavos): int
    {
        return $plan->monthlyCost($gmvCentavos);
    }

    /**
     * The cheapest plan for a given monthly GMV, using each plan's real cost
     * rather than the marketing crossover numbers.
     */
    public function bestPlanFor(int $gmvCentavos): ?Plan
    {
        return $this->plans()
            ->sortBy(fn (Plan $plan) => $plan->monthlyCost($gmvCentavos))
            ->first();
    }

    /**
     * Move a shop onto a plan.
     *
     * Closes the running subscription, opens a new one, copies the rate onto the
     * shop, and invoices the new monthly fee. Past invoices are left alone.
     */
    public function upgrade(Shop $shop, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($shop, $plan) {
            $now = Carbon::now();

            $shop->subscriptions()
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'ends_at' => $now,
                ]);

            $subscription = $shop->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $now,
            ]);

            // Only these two fields. Every payout already written keeps its own
            // rate snapshot, so history is unaffected.
            $shop->update([
                'plan' => $plan->key,
                'commission_rate' => $plan->commission_rate,
            ]);

            if ($plan->monthly_fee > 0) {
                $this->issueInvoice($shop, $subscription, $plan, $now);
            }

            return $subscription;
        });
    }

    private function issueInvoice(Shop $shop, Subscription $subscription, Plan $plan, Carbon $issuedAt): Invoice
    {
        return Invoice::create([
            'number' => $this->nextInvoiceNumber($issuedAt),
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'amount' => $plan->monthly_fee,
            'status' => 'open',
            'period_start' => $issuedAt->copy()->startOfMonth(),
            'period_end' => $issuedAt->copy()->endOfMonth(),
            'issued_at' => $issuedAt,
        ]);
    }

    private function nextInvoiceNumber(Carbon $at): string
    {
        $prefix = 'INV-'.$at->format('Ym').'-';

        $last = Invoice::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
