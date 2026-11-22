<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers an operator actually looks at.
 *
 * Commission is summed from `payments.commission`, never GMV × one rate: shops
 * sit on Starter, Growth and Pro at the same time, so a single rate would be
 * wrong for two thirds of them.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected function getStats(): array
    {
        $gmv = (int) Payment::where('status', 'paid')->sum('amount');
        $commission = (int) Payment::where('status', 'paid')->sum('commission');
        $fees = (int) Payment::where('status', 'paid')->sum('processing_fee');
        $activeShops = Shop::where('status', 'active')->count();
        $pendingShops = Shop::where('status', 'pending')->count();
        $jobsThisMonth = Job::where('created_at', '>=', now()->startOfMonth())->count();
        $settled = Payment::where('status', 'paid')->count();

        return [
            Stat::make('GMV', Money::short($gmv))
                ->description(number_format($settled).' settled payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // Margin, not commission: the processor's fee comes out of every
            // payment before the platform keeps anything.
            Stat::make('Platform margin', Money::signed($commission - $fees))
                ->description(Money::short($commission).' commission − '.Money::short($fees).' fees')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($commission - $fees >= 0 ? 'primary' : 'danger'),

            Stat::make('Active shops', (string) $activeShops)
                ->description($pendingShops > 0
                    ? "{$pendingShops} waiting for approval"
                    : 'Nothing waiting')
                ->descriptionIcon($pendingShops > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-badge')
                ->color($pendingShops > 0 ? 'warning' : 'gray'),

            Stat::make('Jobs this month', (string) $jobsThisMonth)
                ->description('Across every shop')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('gray'),
        ];
    }
}
