<?php

namespace App\Http\Controllers\Console;

use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the shop actually earned, and what we still owe it.
 *
 * Every figure here is derived from `payments`, never recomputed from job
 * totals. That matters because the commission on a payout is a snapshot taken
 * when the money was taken — a shop that upgrades to Pro tomorrow must not see
 * last month's commission retroactively shrink.
 */
class ReportsController extends ConsoleController
{
    /** The ledger shows the most recent slice; the CSV export carries the rest. */
    private const LEDGER_ROWS = 40;

    public function __invoke(): View
    {
        return $this->page(
            'reports',
            [
                'title' => 'Reports',
                'sub' => 'Settled revenue, commission, and what is still held',
            ],
            'console.reports',
            [
                'totals' => $this->totals($this->shop()),
                'month' => $this->monthTotal($this->shop()),
                'weekly' => $this->weekly($this->shop()),
                'services' => $this->topServices($this->shop()),
                'ledger' => $this->ledger($this->shop()),
            ],
            [['label' => 'Export payouts (CSV)', 'url' => route('console.reports.export')]],
        );
    }

    /**
     * Payments for one shop, built from Payment rather than `$shop->payments()`.
     *
     * HasManyThrough appends the through-key (`jobs.shop_id`) to the select list
     * at execution time. MySQL rejects that under only_full_group_by (1140/1055)
     * and SQLite does not implement the rule — so the whole suite passed while
     * the deployed reports page returned 500.
     */
    private function paymentsOf(Shop $shop): Builder
    {
        return Payment::query()
            ->join('jobs', 'jobs.id', '=', 'payments.job_id')
            ->where('jobs.shop_id', $shop->id);
    }

    /**
     * The ledger's totals.
     *
     * Refunded payments are included. A fully refunded payment is not revenue —
     * it drops out of `monthTotal()` and the weekly bars — but its row still
     * carries a **negative** net, because the processor keeps its fee and the
     * shop bears it (#81). Filtering to `status = 'paid'` would make that loss
     * disappear from the books instead of being netted off the next payout.
     *
     * @return array{gross: int, refunded: int, commission: int, commission_refunded: int, fees: int, net: int, awaiting: int, jobs: int}
     */
    private function totals(Shop $shop): array
    {
        $row = $this->paymentsOf($shop)
            ->moved()
            ->selectRaw('coalesce(sum(payments.amount), 0) as gross, coalesce(sum(payments.refunded_amount), 0) as refunded, coalesce(sum(payments.commission), 0) as commission, coalesce(sum(payments.commission_refunded), 0) as commission_refunded, coalesce(sum(payments.processing_fee), 0) as fees, count(payments.id) as jobs')
            ->first();

        $gross = (int) ($row->gross ?? 0);
        $refunded = (int) ($row->refunded ?? 0);
        $commission = (int) ($row->commission ?? 0);
        $commissionRefunded = (int) ($row->commission_refunded ?? 0);
        $fees = (int) ($row->fees ?? 0);

        return [
            'gross' => $gross,
            'refunded' => $refunded,
            'commission' => $commission,
            'commission_refunded' => $commissionRefunded,
            // The processor takes its cut out of the money as it arrives, so the
            // shop was never owed it. It is a deduction, not a platform charge.
            'fees' => $fees,
            // Summing the columns and subtracting the totals gives the same
            // answer as summing each row's net(), and lets one query do it.
            'net' => Payment::netFrom(
                amount: $gross,
                refunded: $refunded,
                commission: $commission,
                commissionRefunded: $commissionRefunded,
                fee: $fees,
            ),
            'awaiting' => $this->heldFor($shop),
            'jobs' => (int) ($row->jobs ?? 0),
        ];
    }

    /**
     * What we are actually holding for the shop, not what the customers paid.
     *
     * This used to sum `amount`, which overstated the figure by the commission
     * and the fee on every unreleased payment — the same mistake #81 fixed in
     * the net. It is the net that will be transferred, so it is the net that is
     * reported.
     */
    private function heldFor(Shop $shop): int
    {
        $row = $this->paymentsOf($shop)
            ->awaitingPayout()
            ->selectRaw('coalesce(sum(payments.amount), 0) as amount, coalesce(sum(payments.refunded_amount), 0) as refunded, coalesce(sum(payments.commission), 0) as commission, coalesce(sum(payments.commission_refunded), 0) as commission_refunded, coalesce(sum(payments.processing_fee), 0) as fees')
            ->first();

        return Payment::netFrom(
            amount: (int) ($row->amount ?? 0),
            refunded: (int) ($row->refunded ?? 0),
            commission: (int) ($row->commission ?? 0),
            commissionRefunded: (int) ($row->commission_refunded ?? 0),
            fee: (int) ($row->fees ?? 0),
        );
    }

    /** Revenue for the current month, matching a plain SUM() over payments. */
    private function monthTotal(Shop $shop): int
    {
        return (int) $this->paymentsOf($shop)
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('payments.amount');
    }

    /**
     * Eight weeks of gross, oldest first, with a height percentage for the bars.
     *
     * @return array<int, array{label: string, gross: int, pct: int, current: bool}>
     */
    private function weekly(Shop $shop): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(7);

        $weeks = [];
        for ($i = 0; $i < 8; $i++) {
            $from = $start->copy()->addWeeks($i);
            $weeks[] = [
                'label' => $from->format('d M'),
                'gross' => 0,
                'pct' => 0,
                'current' => $i === 7,
            ];
        }

        $payments = $this->paymentsOf($shop)
            ->where('payments.status', 'paid')
            ->where('payments.paid_at', '>=', $start)
            ->get(['payments.paid_at', 'payments.amount']);

        foreach ($payments as $payment) {
            if (! $payment->paid_at) {
                continue;
            }

            $index = (int) $start->diffInWeeks($payment->paid_at->copy()->startOfWeek());

            if (isset($weeks[$index])) {
                $weeks[$index]['gross'] += (int) $payment->amount;
            }
        }

        $peak = max(array_column($weeks, 'gross')) ?: 1;

        return array_map(function (array $week) use ($peak): array {
            $week['pct'] = (int) round($week['gross'] / $peak * 100);

            return $week;
        }, $weeks);
    }

    /**
     * The five services that earned the most, from settled payments only.
     *
     * Grouping by id as well as name: two services may share a name.
     *
     * @return Collection<int, object>
     */
    private function topServices(Shop $shop): Collection
    {
        return Payment::query()
            ->join('jobs', 'jobs.id', '=', 'payments.job_id')
            ->join('services', 'services.id', '=', 'jobs.service_id')
            ->where('jobs.shop_id', $shop->id)
            ->where('payments.status', 'paid')
            ->selectRaw('services.id as service_id, services.name as name, count(payments.id) as jobs, sum(payments.amount) as gross, sum(payments.commission) as commission')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('gross')
            ->limit(5)
            ->get();
    }

    /**
     * The payout ledger: one row per payment, gross − commission − fee = net.
     *
     * Includes refunded payments, so the row that explains a drop in the net is
     * on the page rather than only in the database.
     *
     * @return Collection<int, Payment>
     */
    private function ledger(Shop $shop): Collection
    {
        return $this->paymentsOf($shop)
            ->with('job')
            ->moved()
            ->orderByDesc('payments.paid_at')
            ->orderByDesc('payments.id')
            ->limit(self::LEDGER_ROWS)
            ->get(['payments.*']);
    }
}
