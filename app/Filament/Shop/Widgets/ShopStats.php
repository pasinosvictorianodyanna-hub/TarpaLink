<?php

namespace App\Filament\Shop\Widgets;

use App\Filament\Shop\Concerns\ScopesToShop;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * The four numbers a shop owner opens the panel to see.
 *
 * Every figure is a query against this shop's rows, never a cached counter —
 * the panel is small enough that a stale dashboard would be a bug, not a
 * performance win.
 */
class ShopStats extends StatsOverviewWidget
{
    use ScopesToShop;

    protected static ?int $sort = 1;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected function getStats(): array
    {
        $f = $this->figures();

        return [
            Stat::make('Revenue today', Money::short($f['revenue_today']))
                ->description($this->revenueNote($f['revenue_today'], $f['revenue_yesterday']))
                ->descriptionIcon($f['revenue_today'] >= $f['revenue_yesterday']
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($f['revenue_today'] >= $f['revenue_yesterday'] ? 'success' : 'danger')
                ->color('success'),

            Stat::make('Open jobs', (string) $f['open'])
                ->description($f['due_today'] > 0 ? "{$f['due_today']} due today" : 'nothing due today')
                ->descriptionIcon($f['due_today'] > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-badge')
                ->descriptionColor($f['due_today'] > 0 ? 'warning' : 'gray')
                ->color('primary'),

            Stat::make('Avg. job value', Money::short($f['average']))
                ->description('last 30 days, excluding cancelled')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),

            Stat::make(
                'On-time rate',
                $f['collected'] > 0 ? (int) round($f['on_time'] / $f['collected'] * 100).'%' : '—'
            )
                ->description($f['collected'] > 0
                    ? "{$f['on_time']} of {$f['collected']} collected on time"
                    : 'no jobs collected yet')
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray'),
        ];
    }

    /**
     * Every figure the four cards show, in centavos and job counts.
     *
     * @return array{revenue_today: int, revenue_yesterday: int, open: int, due_today: int, average: int, collected: int, on_time: int}
     */
    public function figures(): array
    {
        $shop = $this->shop();

        if (! $shop) {
            return [
                'revenue_today' => 0, 'revenue_yesterday' => 0, 'open' => 0,
                'due_today' => 0, 'average' => 0, 'collected' => 0, 'on_time' => 0,
            ];
        }

        $open = $shop->jobs()->whereNotIn('status', ['collected', 'cancelled']);

        // Cancelled jobs never produced a peso, so they would only drag the
        // average down against work the shop actually did.
        $average = (int) round((float) $shop->jobs()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total'));

        $collected = $shop->jobs()->where('status', 'collected')->whereNotNull('collected_at');

        return [
            'revenue_today' => $this->revenue(Carbon::today(), Carbon::today()->endOfDay()),
            'revenue_yesterday' => $this->revenue(Carbon::yesterday(), Carbon::yesterday()->endOfDay()),
            'open' => (clone $open)->count(),
            'due_today' => (clone $open)
                ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
                ->count(),
            'average' => $average,
            'collected' => (clone $collected)->count(),
            'on_time' => (clone $collected)->whereColumn('collected_at', '<=', 'due_at')->count(),
        ];
    }

    /** Money taken inside a window, in centavos. */
    private function revenue(Carbon $from, Carbon $to): int
    {
        $shop = $this->shop();

        return $shop
            ? (int) $shop->payments()
                ->where('payments.status', 'paid')
                ->whereBetween('payments.paid_at', [$from, $to])
                ->sum('payments.amount')
            : 0;
    }

    private function revenueNote(int $today, int $yesterday): string
    {
        if ($yesterday === 0) {
            return $today > 0 ? 'first takings today' : 'nothing taken yet today';
        }

        $change = (int) round(($today - $yesterday) / $yesterday * 100);

        return ($change >= 0 ? '+' : '−').abs($change).'% vs yesterday';
    }
}
