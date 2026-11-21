<?php

namespace App\Filament\Shop\Widgets;

use App\Filament\Shop\Concerns\ScopesToShop;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Money taken over the last seven days, drawn as bars.
 *
 * A hand-rolled bar chart rather than a ChartWidget: seven bars need no axis,
 * no tooltip library and no canvas, and the panel already draws its eight-week
 * chart the same way. One query, bucketed in PHP — seven SUM()s would be seven
 * round trips for the same numbers.
 */
class LastSevenDays extends Widget
{
    use ScopesToShop;

    protected static ?int $sort = 3;

    protected static ?bool $isLazyLoaded = false;

    /** The other two of five columns, so it sits beside Due today. */
    protected int|string|array $columnSpan = 2;

    protected string $view = 'filament.shop.widgets.last-seven-days';

    /** @var array<int, array{label: string, date: string, total: int, pct: int, current: bool}>|null */
    private ?array $cachedDays = null;

    /**
     * Seven days ending today, oldest first, with a bar height per day.
     *
     * @return array<int, array{label: string, date: string, total: int, pct: int, current: bool}>
     */
    public function days(): array
    {
        return $this->cachedDays ??= $this->buildDays();
    }

    public function weekTotal(): int
    {
        return array_sum(array_column($this->days(), 'total'));
    }

    protected function getViewData(): array
    {
        return [
            'days' => $this->days(),
            'total' => $this->weekTotal(),
        ];
    }

    /**
     * @return array<int, array{label: string, date: string, total: int, pct: int, current: bool}>
     */
    private function buildDays(): array
    {
        $shop = $this->shop();
        $start = Carbon::today()->subDays(6);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[] = [
                'label' => substr($day->format('D'), 0, 1),
                'date' => $day->format('d M'),
                'total' => 0,
                'pct' => 0,
                'current' => $i === 6,
            ];
        }

        if ($shop) {
            $payments = $shop->payments()
                ->where('payments.status', 'paid')
                ->whereBetween('payments.paid_at', [$start, Carbon::today()->endOfDay()])
                ->get(['payments.paid_at', 'payments.amount']);

            foreach ($payments as $payment) {
                if (! $payment->paid_at) {
                    continue;
                }

                $index = (int) $start->diffInDays($payment->paid_at->copy()->startOfDay());

                if (isset($days[$index])) {
                    $days[$index]['total'] += (int) $payment->amount;
                }
            }
        }

        $peak = max(array_column($days, 'total')) ?: 1;

        foreach ($days as $index => $day) {
            $days[$index]['pct'] = (int) round($day['total'] / $peak * 100);
        }

        return $days;
    }
}
