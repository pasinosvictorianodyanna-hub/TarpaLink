<?php

namespace App\Filament\Shop\Widgets;

use App\Filament\Shop\Concerns\ScopesToShop;
use App\Models\Job;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What has to leave the shop today.
 *
 * "Due" means `due_at` falls inside today's calendar day — not "overdue", which
 * is a separate problem and belongs in Needs attention. Collected and cancelled
 * jobs are excluded because neither is still on the floor.
 */
class DueToday extends Widget
{
    use ScopesToShop;

    protected static ?int $sort = 2;

    protected static ?bool $isLazyLoaded = false;

    /** Three of five columns: wide enough for the spec, not the whole row. */
    protected int|string|array $columnSpan = 3;

    protected string $view = 'filament.shop.widgets.due-today';

    /** @return Collection<int, Job> */
    public function jobs(): Collection
    {
        $shop = $this->shop();

        if (! $shop) {
            return collect();
        }

        return $shop->jobs()
            ->with(['user', 'service.options'])
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->orderBy('due_at')
            ->limit(6)
            ->get();
    }

    protected function getViewData(): array
    {
        $jobs = $this->jobs();

        return [
            'jobs' => $jobs,
            'count' => $jobs->count(),
        ];
    }
}
