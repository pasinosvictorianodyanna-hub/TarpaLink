<?php

namespace App\Filament\Shop\Pages;

use App\Models\Shop;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * What the shop actually earned, and what we still owe it.
 *
 * Every figure here is derived from `payments`, never recomputed from job totals.
 * That matters because the commission on a payout is a snapshot taken when the
 * money was taken — a shop that upgrades to Pro tomorrow must not see last
 * month's commission retroactively shrink.
 */
class Reports extends Page
{
    protected string $view = 'filament.shop.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Reports';

    public function shop(): ?Shop
    {
        return auth()->user()?->currentShop();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export payouts (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('filament.shop.reports.export'))
                ->visible(fn (): bool => $this->shop() !== null),
        ];
    }

    /**
     * Settled revenue and the commission split, plus what is still held.
     *
     * @return array{gross: int, commission: int, net: int, awaiting: int, jobs: int}
     */
    public function totals(): array
    {
        $shop = $this->shop();

        if (! $shop) {
            return ['gross' => 0, 'commission' => 0, 'net' => 0, 'awaiting' => 0, 'jobs' => 0];
        }

        $row = $shop->payments()
            ->where('payments.status', 'paid')
            ->selectRaw('coalesce(sum(payments.amount), 0) as gross, coalesce(sum(payments.commission), 0) as commission, count(payments.id) as jobs')
            ->first();

        $gross = (int) ($row->gross ?? 0);
        $commission = (int) ($row->commission ?? 0);

        return [
            'gross' => $gross,
            'commission' => $commission,
            'net' => $gross - $commission,
            'awaiting' => (int) $shop->payments()->awaitingPayout()->sum('payments.amount'),
            'jobs' => (int) ($row->jobs ?? 0),
        ];
    }

    /** Revenue for the current month, matching a plain SUM() over payments. */
    public function monthTotal(): int
    {
        $shop = $this->shop();

        return $shop
            ? (int) $shop->payments()
                ->where('payments.status', 'paid')
                ->whereBetween('payments.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('payments.amount')
            : 0;
    }

    /**
     * Eight weeks of gross, oldest first, with a height percentage for the bars.
     *
     * @return array<int, array{label: string, gross: int, pct: int, current: bool}>
     */
    public function weekly(): array
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

        $shop = $this->shop();

        if ($shop) {
            $payments = $shop->payments()
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
     * @return Collection<int, object>
     */
    public function topServices(): Collection
    {
        $shop = $this->shop();

        if (! $shop) {
            return collect();
        }

        return $shop->payments()
            ->where('payments.status', 'paid')
            ->join('services', 'services.id', '=', 'jobs.service_id')
            ->selectRaw('services.name as name, count(payments.id) as jobs, sum(payments.amount) as gross, sum(payments.commission) as commission')
            ->groupBy('services.name')
            ->orderByDesc('gross')
            ->limit(5)
            ->get();
    }

    /**
     * The payout ledger: one row per payment, gross − commission = net.
     *
     * @return Collection<int, \App\Models\Payment>
     */
    public function ledger(): Collection
    {
        $shop = $this->shop();

        if (! $shop) {
            return collect();
        }

        return $shop->payments()
            ->with('job')
            ->where('payments.status', 'paid')
            ->orderByDesc('payments.paid_at')
            ->limit(40)
            ->get();
    }

    public function money(?int $centavos): string
    {
        return Money::format($centavos);
    }
}
