<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Job;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Jobs placed against jobs completed, day by day.
 *
 * The design asks for this rather than a revenue line: a platform that is
 * growing its GMV while its completion line flattens is not healthy, and a
 * single money curve hides that.
 *
 * Grouped in PHP, not SQL: day boundaries follow the operator's timezone, which
 * `DATE()` in the database does not know about.
 */
class JobsPerDay extends ChartWidget
{
    protected ?string $heading = 'Jobs per day';

    protected static ?int $sort = 3;

    /** Render inline: a dashboard should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    private const DAYS = 14;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = Carbon::today()->subDays(self::DAYS - 1);

        $labels = [];
        $placed = [];
        $completed = [];

        for ($i = 0; $i < self::DAYS; $i++) {
            $labels[] = $start->copy()->addDays($i)->format('d M');
            $placed[] = 0;
            $completed[] = 0;
        }

        foreach (Job::where('created_at', '>=', $start)->get(['created_at']) as $job) {
            $index = $start->diffInDays($job->created_at->copy()->startOfDay());

            if (isset($placed[$index])) {
                $placed[$index]++;
            }
        }

        foreach (Job::where('status', 'collected')->where('collected_at', '>=', $start)->get(['collected_at']) as $job) {
            $index = $start->diffInDays($job->collected_at->copy()->startOfDay());

            if (isset($completed[$index])) {
                $completed[$index]++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Placed',
                    'data' => $placed,
                    'borderColor' => '#0092E4',
                    'backgroundColor' => 'rgba(0, 146, 228, .12)',
                    'fill' => true,
                    'tension' => .35,
                    'pointRadius' => 2,
                ],
                [
                    'label' => 'Completed',
                    'data' => $completed,
                    'borderColor' => '#0E9F6E',
                    'backgroundColor' => 'rgba(14, 159, 110, .12)',
                    'fill' => true,
                    'tension' => .35,
                    'pointRadius' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        // Static options only — a JS callback that fails to compile takes the
        // whole chart down with it.
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => ['boxWidth' => 8, 'boxHeight' => 8, 'usePointStyle' => true],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $start = Carbon::today()->subDays(self::DAYS - 1);

        $placed = Job::where('created_at', '>=', $start)->count();
        $completed = Job::where('status', 'collected')->where('collected_at', '>=', $start)->count();

        return "{$placed} placed, {$completed} collected in the last ".self::DAYS.' days';
    }
}
