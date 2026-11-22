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
 *
 * The processor's fee is **not** subtracted from it. Since #81 the fee comes off
 * the shop's payout — `net = amount − commission − fee` — so the platform
 * receives the gross, pays the shop the net, and keeps the commission. The fee
 * is a deduction the shop sees, not a cost we carry; subtracting it here would
 * charge the platform for a fee it never paid. Refunded commission is subtracted,
 * because that is money we gave back.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected function getStats(): array
    {
        $gmv = (int) Payment::moved()->sum('amount') - (int) Payment::moved()->sum('refunded_amount');
        $commission = (int) Payment::moved()->sum('commission') - (int) Payment::moved()->sum('commission_refunded');
        $fees = (int) Payment::moved()->sum('processing_fee');
        $activeShops = Shop::where('status', 'active')->count();
        $pendingShops = Shop::where('status', 'pending')->count();
        $jobsThisMonth = Job::where('created_at', '>=', now()->startOfMonth())->count();
        $settled = Payment::moved()->count();

        return [
            Stat::make('GMV', Money::short($gmv))
                ->description(number_format($settled).' settled payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Commission earned', Money::short($commission))
                ->description(Money::short($fees).' of processor fees, borne by shops')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($commission >= 0 ? 'primary' : 'danger'),

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
