<?php

namespace App\Filament\Shop\Pages;

use App\Filament\Shop\Concerns\ScopesToShop;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * The shop's front door: what is in the queue, what is due, what is stuck.
 *
 * There is no "New job" button, and that is deliberate — `JobResource` has no
 * create page because a job is a priced snapshot the storefront produces. A
 * hand-keyed job would have to invent a price outside `PriceCalculator`, which
 * is exactly the drift the matrix exists to prevent.
 */
class Dashboard extends BaseDashboard
{
    use ScopesToShop;

    protected static ?string $title = 'Dashboard';

    /**
     * Five columns, not the stock two.
     *
     * The design puts "Due today" beside "Last 7 days" at roughly 3:2 — the
     * table needs the width for the spec column, and the seven bars do not. A
     * two-column grid would split them down the middle and wrap every row.
     *
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 5;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $shop = $this->shop();

        if (! $shop) {
            return null;
        }

        $open = $shop->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count();

        $dueToday = $shop->jobs()
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        return now()->format('l, j F')
            .' · '.$open.' '.($open === 1 ? 'job' : 'jobs').' in the queue'
            .', '.$dueToday.' due today';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export payouts')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(route('filament.shop.reports.export')),
        ];
    }
}
