<?php

namespace App\Filament\Shop\Pages;

use App\Models\Job;
use App\Models\Payment;
use App\Support\CsvExport;
use App\Support\Money;
use App\Support\ShopContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Revenue by week, revenue by service, and a CSV of both. See docs/ISSUES.md #7.
 *
 * Every number here comes from captured payments, not from job totals: a quote
 * is not money.
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.shop.pages.reports';

    /** @var array<int, array{label: string, total: int}> */
    public array $weeks = [];

    /** @var array<int, array{name: string, jobs: int, revenue: int}> */
    public array $services = [];

    public int $totalRevenue = 0;

    public int $totalCommission = 0;

    public int $paidJobs = 0;

    public function mount(): void
    {
        $shop = app(ShopContext::class)->shop();

        abort_if($shop === null, 403);

        $this->weeks = $this->revenueByWeek($shop->id);
        $this->services = $this->revenueByService($shop->id);

        $paid = Payment::query()
            ->whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid');

        $this->totalRevenue = (int) (clone $paid)->sum('amount');
        $this->totalCommission = (int) (clone $paid)->sum('commission');
        $this->paidJobs = (clone $paid)->count();
    }

    /** @return array<int, array{label: string, total: int}> */
    private function revenueByWeek(int $shopId): array
    {
        return collect(range(7, 0))
            ->map(function (int $weeksAgo) use ($shopId): array {
                $start = Carbon::now()->subWeeks($weeksAgo)->startOfWeek();
                $end = Carbon::now()->subWeeks($weeksAgo)->endOfWeek();

                $total = (int) Payment::query()
                    ->whereHas('job', fn ($q) => $q->where('shop_id', $shopId))
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('amount');

                return [
                    'label' => $start->format('d M'),
                    'total' => $total,
                ];
            })
            ->all();
    }

    /** @return array<int, array{name: string, jobs: int, revenue: int}> */
    private function revenueByService(int $shopId): array
    {
        return Job::query()
            ->where('jobs.shop_id', $shopId)
            ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
            ->join('services', 'services.id', '=', 'jobs.service_id')
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as name, COUNT(*) as jobs, SUM(jobs.total) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'jobs' => (int) $row->jobs,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /** Highest week total, so the bars can be scaled without a chart library. */
    public function peakWeek(): int
    {
        return max(1, collect($this->weeks)->max('total') ?? 1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportWeeks')
                ->label('Export weekly CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => CsvExport::stream(
                    'tarpalink-revenue-'.now()->format('Y-m-d').'.csv',
                    ['Week starting', 'Revenue (PHP)'],
                    collect($this->weeks)->map(fn (array $week) => [
                        $week['label'],
                        number_format($week['total'] / 100, 2, '.', ''),
                    ])->all(),
                )),

            Action::make('exportServices')
                ->label('Export services CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => CsvExport::stream(
                    'tarpalink-services-'.now()->format('Y-m-d').'.csv',
                    ['Service', 'Paid jobs', 'Revenue (PHP)'],
                    collect($this->services)->map(fn (array $service) => [
                        $service['name'],
                        $service['jobs'],
                        number_format($service['revenue'] / 100, 2, '.', ''),
                    ])->all(),
                )),
        ];
    }

    /** Exposed to the view so formatting stays in one place. */
    public function money(int $centavos): string
    {
        return Money::format($centavos);
    }

    public function weeksCollection(): Collection
    {
        return collect($this->weeks);
    }
}
