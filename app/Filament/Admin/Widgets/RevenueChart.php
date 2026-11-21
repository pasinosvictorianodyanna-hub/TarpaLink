<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Payment;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Eight weeks of platform GMV.
 *
 * Grouped in PHP rather than SQL: week boundaries depend on the timezone the
 * operator thinks in, and `WEEK()` in SQL does not know about it.
 */
class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Platform GMV, last 8 weeks';

    protected static ?int $sort = 3;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(7);

        $labels = [];
        $data = [];

        for ($i = 0; $i < 8; $i++) {
            $from = $start->copy()->addWeeks($i);
            $labels[] = $from->format('d M');
            $data[] = 0;
        }

        $payments = Payment::where('status', 'paid')
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'amount']);

        foreach ($payments as $payment) {
            if (! $payment->paid_at) {
                continue;
            }

            $index = (int) $start->diffInWeeks($payment->paid_at->copy()->startOfWeek());

            if (isset($data[$index])) {
                $data[$index] += (int) $payment->amount;
            }
        }

        // Pesos, not centavos, so the axis reads like money.
        $data = array_map(fn (int $centavos): float => round($centavos / 100, 2), $data);

        return [
            'datasets' => [
                [
                    'label' => 'GMV (₱)',
                    'data' => $data,
                    'borderColor' => '#D97706',
                    'backgroundColor' => 'rgba(217, 119, 6, .12)',
                    'fill' => true,
                    'tension' => .35,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        // Static options only. Money formatting is done on the dataset label and
        // in `getDescription()`, not through a JS callback: a callback that fails
        // to compile takes the whole chart down with it, and the axis is readable
        // enough without one.
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $total = (int) Payment::where('status', 'paid')
            ->where('paid_at', '>=', Carbon::now()->startOfWeek()->subWeeks(7))
            ->sum('amount');

        return Money::short($total).' in the last eight weeks';
    }
}
