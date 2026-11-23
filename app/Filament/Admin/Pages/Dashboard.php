<?php

namespace App\Filament\Admin\Pages;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Billing;
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

        // Refunds come off both: GMV is what was actually taken, and a commission
        // we gave back is not revenue. `moved()` keeps a refunded payment in the
        // sums so its reversal is subtracted rather than silently dropped.
        $gmv = (int) Payment::moved()->whereBetween('paid_at', $month)->sum('amount')
            - (int) Payment::moved()->whereBetween('paid_at', $month)->sum('refunded_amount');
        $gmvBefore = (int) Payment::moved()->whereBetween('paid_at', $lastMonth)->sum('amount')
            - (int) Payment::moved()->whereBetween('paid_at', $lastMonth)->sum('refunded_amount');

        // Commission is earned when the job is **collected**, which is what the
        // published pricing promises ("per completed job"). Counting it at
        // settlement recognised revenue on jobs still on the press — and on jobs
        // that were cancelled before anything was printed, where the money had
        // to go back to the customer.
        $commission = $this->commissionEarned($month);
        $commissionHeld = $this->commissionHeld();
        $fees = (int) Payment::moved()->whereBetween('paid_at', $month)->sum('processing_fee');
        $settled = Payment::moved()->whereBetween('paid_at', $month)->count();

        $jobs = Job::whereBetween('created_at', $month)->count();
        $jobsBefore = Job::whereBetween('created_at', $lastMonth)->count();

        $active = Shop::where('status', 'active')->count();
        $pending = Shop::where('status', 'pending')->count();

        // Subscription revenue is the platform's second pot, and it was missing
        // from this screen entirely: only commission was counted, so a platform
        // could bill nothing for a year and the overview would look unchanged.
        // `unbilled` is the number that matters most — a shop on a paid plan
        // with no subscription earns the discounted rate and pays no fee.
        $subscriptionRevenue = (int) Invoice::where('status', Invoice::PAID)
            ->whereBetween('paid_at', $month)
            ->sum('amount');
        $outstanding = (int) Invoice::unpaid()->sum('amount');
        $unbilled = app(Billing::class)->unbilledShops()->count();
        $paidSubscriptions = Subscription::where('status', 'active')
            ->whereHas('plan', fn ($query) => $query->where('price', '>', 0))
            ->count();

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
                // Not "commission − fees". The processor's fee comes off the
                // shop's payout (#81), so we receive the gross, pay the shop the
                // net, and keep the commission. Subtracting the fee here would
                // charge us for a fee we never paid.
                'value' => Money::short($commission),
                'note' => $commissionHeld > 0
                    ? Money::short($commissionHeld).' held on jobs in progress'
                    : 'every completed job is counted',
                'sub' => Money::short($fees).' of fees, borne by shops',
                'tone' => $commission >= 0 ? 'good' : 'bad',
            ],
            'subscription' => [
                'label' => 'Subscription revenue',
                'value' => Money::short($subscriptionRevenue),
                'note' => $unbilled > 0
                    ? $unbilled.' shop(s) on a paid plan, unbilled'
                    : ($outstanding > 0 ? Money::short($outstanding).' still outstanding' : 'all collected'),
                'sub' => $paidSubscriptions.' active paid subscription(s)',
                // A shop that carries the rate but never a bill is a wait state,
                // not a failure — it needs a person, not a retry.
                'tone' => $unbilled > 0 ? 'wait' : 'good',
            ],
        ];
    }

    /**
     * Commission on jobs that have actually been collected, inside a window.
     *
     * A refund reverses the commission in proportion, so a job that was
     * collected and then partly refunded counts only what we kept.
     *
     * @param  array{0: Carbon, 1: Carbon}  $window
     */
    private function commissionEarned(array $window): int
    {
        $query = fn () => Payment::moved()->onCollectedJobs()->whereBetween('paid_at', $window);

        return (int) $query()->sum('commission') - (int) $query()->sum('commission_refunded');
    }

    /**
     * Commission received but not yet earned, because the job is unfinished.
     *
     * Reported beside the total rather than added to it. It is money the platform
     * is already holding, so leaving it out silently would look like a shortfall;
     * counting it would be the mistake this replaced.
     */
    private function commissionHeld(): int
    {
        return (int) Payment::where('status', 'paid')
            ->whereHas('job', fn ($job) => $job->whereNotIn('status', ['collected', 'cancelled']))
            ->sum('commission');
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
            // `id` breaks ties. The seeder places a batch of jobs in the same
            // second, so twelve rows can share the newest `created_at` and the
            // feed only shows eight of them — without a total order, which four
            // get dropped changes between loads and a job appears to vanish.
            ->orderByDesc('id')
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
