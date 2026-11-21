<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform health at a glance. See docs/ISSUES.md #8.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $gmv = (int) Payment::where('status', 'paid')->sum('amount');

        // Sum of the snapshotted amounts, not GMV × one rate: shops sit on
        // different plans, so a single multiplier would be wrong for most of them.
        $commission = (int) Payment::where('status', 'paid')->sum('commission');

        $activeShops = Shop::where('status', 'active')->count();
        $pendingShops = Shop::where('status', 'pending')->count();
        $jobsInFlight = Job::whereIn('status', ['quoted', 'awaiting_proof', 'approved', 'in_production', 'ready'])->count();

        return [
            Stat::make('Gross merchandise value', Money::format($gmv))
                ->description($jobsInFlight.' jobs in flight')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chartColor('success'),

            Stat::make('Commission earned', Money::format($commission))
                ->description('Sum of captured payments')
                ->descriptionIcon('heroicon-m-banknotes'),

            Stat::make('Active shops', (string) $activeShops)
                ->description($pendingShops > 0 ? $pendingShops.' awaiting approval' : 'No pending applications')
                ->descriptionIcon($pendingShops > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->descriptionColor($pendingShops > 0 ? 'warning' : 'success'),

            Stat::make('Jobs', (string) Job::count())
                ->description(Job::where('status', 'collected')->count().' collected')
                ->descriptionIcon('heroicon-m-clipboard-document-check'),
        ];
    }
}
