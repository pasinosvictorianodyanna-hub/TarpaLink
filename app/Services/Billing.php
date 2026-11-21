<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Plan changes.
 *
 * The one rule that matters: changing a plan updates the shop's rate going
 * forward and never rewrites a payout that has already been settled.
 * `payments.commission` is an absolute centavo amount captured at payment time,
 * so it is structurally immune to this — nothing here touches it.
 */
final class Billing
{
    /**
     * Move a shop onto a plan, closing the previous subscription and issuing an
     * invoice when the plan costs anything.
     */
    public function subscribe(Shop $shop, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($shop, $plan): Subscription {
            $shop->subscriptions()
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'ends_at' => now()]);

            $subscription = Subscription::create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            if (! $plan->isFree()) {
                $this->issueInvoice($shop, $subscription, $plan);
            }

            // Only these two columns change. Historical payments keep the rate
            // they were settled at.
            $shop->update([
                'plan' => $plan->code,
                'commission_rate' => $plan->commission_rate,
            ]);

            return $subscription;
        });
    }

    private function issueInvoice(Shop $shop, Subscription $subscription, Plan $plan): Invoice
    {
        return Invoice::create([
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'number' => $this->nextNumber(),
            'amount' => $plan->price,
            'status' => 'open',
            'issued_at' => now(),
        ]);
    }

    /** INV-202611-0001 — sequential per calendar month. */
    private function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        $used = Invoice::where('number', 'like', $prefix.'%')->count();

        do {
            $candidate = $prefix.str_pad((string) (++$used), 4, '0', STR_PAD_LEFT);
        } while (Invoice::where('number', $candidate)->exists());

        return $candidate;
    }

    /**
     * What a shop would pay on each plan at a given monthly GMV, in centavos.
     *
     * @return array<int, array{plan: Plan, monthly_fee: int, commission: int, total: int}>
     */
    public function compareAt(int $gmv): array
    {
        return Plan::orderBy('sort')->get()
            ->map(fn (Plan $plan): array => [
                'plan' => $plan,
                'monthly_fee' => $plan->price,
                'commission' => (int) round($gmv * $plan->commission_rate / 10000),
                'total' => $plan->price + (int) round($gmv * $plan->commission_rate / 10000),
            ])
            ->all();
    }

    /** Cheapest plan for a given monthly GMV. */
    public function bestPlanFor(int $gmv): ?Plan
    {
        $rows = $this->compareAt($gmv);

        usort($rows, fn (array $a, array $b): int => $a['total'] <=> $b['total']);

        return $rows[0]['plan'] ?? null;
    }
}
