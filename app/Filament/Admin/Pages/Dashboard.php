<?php

namespace App\Filament\Admin\Pages;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\ShopApproval;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The platform overview, in the design system.
 *
 * Filament's stock dashboard renders widgets in Filament's own card language.
 * The prototype draws this screen with its own stat tiles, a bar chart and two
 * lists, so the page renders a view instead — the same approach the shop
 * dashboard's widgets already use.
 *
 * Every figure is computed here rather than in a widget, so the header action
 * and the tiles can never disagree about what "this month" means.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Platform overview';

    protected string $view = 'filament.admin.pages.dashboard';

    private const CHART_DAYS = 14;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export report')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportPayments()),
        ];
    }

    /** @return array<string, mixed> */
    public function tiles(): array
    {
        $month = [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        $lastMonth = [Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->endOfMonth()];

        $gmv = (int) Payment::where('status', 'paid')->whereBetween('paid_at', $month)->sum('amount');
        $gmvBefore = (int) Payment::where('status', 'paid')->whereBetween('paid_at', $lastMonth)->sum('amount');

        $commission = (int) Payment::where('status', 'paid')->whereBetween('paid_at', $month)->sum('commission');
        $settled = Payment::where('status', 'paid')->whereBetween('paid_at', $month)->count();

        $jobs = Job::whereBetween('created_at', $month)->count();
        $jobsBefore = Job::whereBetween('created_at', $lastMonth)->count();

        $active = Shop::where('status', 'active')->count();
        $pending = Shop::where('status', 'pending')->count();

        return [
            'gmv' => [
                'label' => 'Platform GMV',
                'value' => Money::short($gmv),
                'note' => $this->delta($gmv, $gmvBefore),
                'sub' => number_format($settled).' settled payments',
                'tone' => $gmv >= $gmvBefore ? 'good' : 'bad',
            ],
            'jobs' => [
                'label' => 'Jobs this month',
                'value' => number_format($jobs),
                'note' => $this->delta($jobs, $jobsBefore),
                'sub' => 'across every shop',
                'tone' => $jobs >= $jobsBefore ? 'good' : 'bad',
            ],
            'shops' => [
                'label' => 'Active shops',
                'value' => number_format($active),
                'note' => $pending > 0 ? $pending.' awaiting approval' : 'none waiting',
                'sub' => 'can accept orders',
                'tone' => $pending > 0 ? 'wait' : 'good',
            ],
            'commission' => [
                'label' => 'Commission earned',
                'value' => Money::short($commission),
                'note' => $gmv > 0 ? round($commission / $gmv * 100, 1).'% blended' : 'no revenue yet',
                'sub' => 'this month',
                'tone' => 'good',
            ],
        ];
    }

    private function delta(int $now, int $before): string
    {
        if ($before === 0) {
            return $now > 0 ? 'first month with activity' : 'nothing yet';
        }

        $change = (int) round(($now - $before) / $before * 100);

        return ($change >= 0 ? '↑ ' : '↓ ').abs($change).'% vs last month';
    }

    /**
     * Placed against completed, day by day.
     *
     * @return array{labels: array<int, string>, placed: array<int, int>, completed: array<int, int>, max: int}
     */
    public function chart(): array
    {
        $start = Carbon::today()->subDays(self::CHART_DAYS - 1);

        $labels = [];
        $placed = [];
        $completed = [];

        for ($i = 0; $i < self::CHART_DAYS; $i++) {
            $labels[] = $start->copy()->addDays($i)->format('j');
            $placed[] = 0;
            $completed[] = 0;
        }

        foreach (Job::where('created_at', '>=', $start)->get(['created_at']) as $job) {
            $i = (int) $start->diffInDays($job->created_at->copy()->startOfDay());
            $placed[$i] = ($placed[$i] ?? 0) + 1;
        }

        foreach (Job::where('status', 'collected')->where('collected_at', '>=', $start)->get(['collected_at']) as $job) {
            $i = (int) $start->diffInDays($job->collected_at->copy()->startOfDay());
            $completed[$i] = ($completed[$i] ?? 0) + 1;
        }

        return [
            'labels' => $labels,
            'placed' => $placed,
            'completed' => $completed,
            'max' => max(1, max($placed), max($completed)),
        ];
    }

    /** @return Collection<int, Shop> */
    public function pendingShops(): Collection
    {
        return Shop::where('status', 'pending')
            ->withCount('services')
            ->orderBy('created_at')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Job> */
    public function latestJobs(): Collection
    {
        return Job::with(['shop', 'user', 'service'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, Payment> */
    public function paymentsNeedingAttention(): Collection
    {
        return Payment::with(['job.shop'])
            ->where(function ($query): void {
                $query->where('status', 'failed')
                    ->orWhere(fn ($q) => $q->where('status', 'pending')->where('attempts', '>=', 3));
            })
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
    }

    public function approve(int $shopId): void
    {
        $shop = Shop::findOrFail($shopId);

        try {
            app(ShopApproval::class)->approve($shop, auth()->user());
        } catch (Throwable $e) {
            report($e);

            Notification::make()->title('Could not approve '.$shop->name)->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($shop->name.' approved')
            ->body('The owner can sign in to the shop panel now.')
            ->success()
            ->send();
    }

    /**
     * Every captured payment on the platform, one row each.
     *
     * Streamed rather than assembled in memory: this is the one export that grows
     * with the whole marketplace, not with one shop.
     */
    public function exportPayments(): StreamedResponse
    {
        $payments = Payment::query()
            ->with(['job.shop', 'job.user', 'job.service'])
            ->where('status', 'paid')
            ->orderBy('paid_at')
            ->cursor();

        $filename = 'tarpalink-platform-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Captured', 'Shop', 'Job', 'Service', 'Customer',
                'Gross (PHP)', 'Commission (PHP)', 'Net (PHP)', 'Payout',
            ]);

            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->paid_at?->format('Y-m-d') ?? '',
                    $payment->job?->shop?->name ?? '',
                    $payment->job?->reference ?? '',
                    $payment->job?->service?->name ?? '',
                    $payment->job?->user?->name ?? '',
                    number_format($payment->amount / 100, 2, '.', ''),
                    number_format($payment->commission / 100, 2, '.', ''),
                    number_format($payment->net() / 100, 2, '.', ''),
                    $payment->payout_status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
