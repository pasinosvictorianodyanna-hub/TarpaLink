<?php

namespace App\Filament\Shop\Widgets;

use App\Models\Payment;
use App\Support\Money;
use App\Support\ShopContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** What an owner wants to know before their coffee: work in, money out. */
class ShopStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $shop = app(ShopContext::class)->shop();

        if (! $shop) {
            return [];
        }

        $monthRevenue = (int) $shop->payments()
            ->where('payments.status', 'paid')
            ->where('payments.paid_at', '>=', now()->startOfMonth())
            ->sum('payments.amount');

        $pendingPayout = (int) $shop->payouts()
            ->whereIn('status', ['pending', 'scheduled'])
            ->sum('net');

        $inFlight = $shop->jobs()
            ->whereIn('status', ['quoted', 'awaiting_proof', 'approved', 'in_production', 'ready'])
            ->count();

        $ready = $shop->jobs()->where('status', 'ready')->count();

        return [
            Stat::make('Jobs in flight', (string) $inFlight)
                ->description($ready.' waiting for pickup')
                ->descriptionIcon('heroicon-m-clipboard-document-list'),

            Stat::make('Revenue this month', Money::format($monthRevenue))
                ->description('Captured payments')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chartColor('success'),

            Stat::make('Pending payout', Money::format($pendingPayout))
                ->description('After platform commission')
                ->descriptionIcon('heroicon-m-banknotes'),

            Stat::make('Commission rate', $shop->commissionLabel())
                ->description(ucfirst($shop->plan).' plan')
                ->descriptionIcon('heroicon-m-receipt-percent'),
        ];
    }
}
