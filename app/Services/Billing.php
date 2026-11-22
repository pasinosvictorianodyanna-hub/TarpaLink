<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            // A switch mid-month supersedes the bill for that month. Without
            // this the shop is collectable twice for the same period — once for
            // the plan it left and once for the plan it joined — and the old
            // invoice stays `open` forever because nobody will ever pay it.
            //
            // Only the month the switch lands in: an unpaid invoice from an
            // earlier month is arrears, and upgrading does not erase arrears.
            $this->voidInvoicesForPeriodContaining($shop, now());

            $subscription = Subscription::create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            if (! $plan->isFree()) {
                [$start, $end] = $this->periodFor($subscription, now());
                $this->issueInvoice($shop, $subscription, $plan, $start, $end);
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

    /**
     * Void the shop's open invoices for the month containing `$when`.
     *
     * Matches on `period_start` where there is one, and falls back to `issued_at`
     * for invoices written before periods existed — otherwise the legacy rows
     * would survive every switch and go on being collectable.
     */
    private function voidInvoicesForPeriodContaining(Shop $shop, CarbonInterface $when): void
    {
        $from = $when->copy()->startOfMonth();
        $to = $when->copy()->endOfMonth();

        Invoice::query()
            ->where('shop_id', $shop->id)
            ->where('status', Invoice::OPEN)
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(fn ($legacy) => $legacy
                        ->whereNull('period_start')
                        ->whereBetween('issued_at', [$from, $to]));
            })
            ->update(['status' => Invoice::VOID]);
    }

    /**
     * Bill every active paid subscription for the period it is in.
     *
     * Run daily. Idempotent by construction: the period is derived from the
     * subscription's anniversary, and an invoice already exists for it, so a
     * second run on the same day issues nothing. That is the property that makes
     * this safe to schedule without a lock — the alternative, remembering the
     * last run, breaks the first time the scheduler misses a night.
     *
     * A free plan issues nothing, and a `void` invoice does not suppress the
     * period: voiding means "this bill was wrong", not "this month is free".
     *
     * @return array<int, Invoice> the invoices actually created
     */
    public function issueDueInvoices(?CarbonInterface $today = null): array
    {
        $today = $today ?? now();
        $issued = [];

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->with(['shop', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $plan = $subscription->plan;
            $shop = $subscription->shop;

            // A cancelled shop keeps its subscription row but should not be
            // billed for a service it cannot use.
            if ($plan === null || $shop === null || $plan->isFree() || $shop->status !== 'active') {
                continue;
            }

            [$start, $end] = $this->periodFor($subscription, $today);

            $alreadyBilled = Invoice::where('subscription_id', $subscription->id)
                ->whereDate('period_start', $start)
                ->where('status', '!=', Invoice::VOID)
                ->exists();

            if ($alreadyBilled) {
                continue;
            }

            $issued[] = $this->issueInvoice($shop, $subscription, $plan, $start, $end);
        }

        return $issued;
    }

    /**
     * Record that an invoice was paid.
     *
     * Subscriptions are settled by GCash or a bank transfer, not by a card we
     * hold, so this is always a person saying "the money arrived". It stamps who
     * said so, and refuses to overwrite an earlier payment or to collect a void
     * bill.
     *
     * @throws ValidationException
     */
    public function recordPayment(Invoice $invoice, ?User $by = null): Invoice
    {
        if ($invoice->isPaid()) {
            throw ValidationException::withMessages([
                'status' => $invoice->number.' was already paid on '.$invoice->paid_at?->format('d M Y').'.',
            ]);
        }

        if ($invoice->status === Invoice::VOID) {
            throw ValidationException::withMessages([
                'status' => $invoice->number.' was voided. Re-issue the period instead of collecting it.',
            ]);
        }

        $invoice->forceFill([
            'status' => Invoice::PAID,
            'paid_at' => now(),
            'paid_by' => $by?->id ?? auth()->id(),
        ])->save();

        return $invoice;
    }

    /** Withdraw an invoice that should never have been issued. */
    public function void(Invoice $invoice): Invoice
    {
        if ($invoice->isPaid()) {
            throw ValidationException::withMessages([
                'status' => 'A paid invoice cannot be voided — refund it instead.',
            ]);
        }

        $invoice->forceFill(['status' => Invoice::VOID])->save();

        return $invoice;
    }

    /**
     * The billing period a subscription is in on a given day.
     *
     * Periods follow the day the subscription started — a shop that signed up on
     * the 15th is billed on the 15th — and `addMonthNoOverflow` keeps a 31st from
     * rolling into March.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function periodFor(Subscription $subscription, CarbonInterface $today): array
    {
        $start = $subscription->started_at->copy()->startOfDay();

        // Walk forward a month at a time. Bounded by `$today`, which is always
        // at or after the start, so this terminates.
        while ($start->copy()->addMonthNoOverflow()->lte($today)) {
            $start = $start->addMonthNoOverflow();
        }

        return [$start, $start->copy()->addMonthNoOverflow()->subDay()];
    }

    private function issueInvoice(
        Shop $shop,
        Subscription $subscription,
        Plan $plan,
        ?CarbonInterface $periodStart = null,
        ?CarbonInterface $periodEnd = null,
    ): Invoice {
        $issuedAt = now();

        return Invoice::create([
            'shop_id' => $shop->id,
            'subscription_id' => $subscription->id,
            'number' => $this->nextNumber(),
            'amount' => $plan->price,
            'status' => Invoice::OPEN,
            'issued_at' => $issuedAt,
            // Net seven. Long enough for a bank transfer to clear, short enough
            // that a shop two weeks late is visibly late.
            'due_at' => ($periodStart?->copy() ?? $issuedAt->copy())->addDays(7),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
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
