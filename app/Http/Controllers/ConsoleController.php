<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Shop;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The shop console.
 *
 * This is the shop side moving out of Filament. Filament is the CMS, and the CMS
 * belongs on the admin side; a shop owner running their day is not editing
 * content, they are reading a queue.
 *
 * The panel still owns Jobs, Production board, Price matrix, Reports and Billing
 * while those are ported, so the sidebar links into it. The moment the last
 * screen moves, the panel registration goes and the links point back here.
 */
class ConsoleController extends Controller
{
    /** Hours of silence before a sent proof counts as stuck. */
    private const PROOF_HOURS = 6;

    /** Hours before an unconfirmed quote counts as stuck. */
    private const QUOTE_HOURS = 24;

    /** Hours a finished job may sit on the shelf before it is chased. */
    private const PICKUP_HOURS = 48;

    /** How many rows a card shows before it stops being a glance. */
    private const LIMIT = 6;

    public function dashboard(Request $request): View
    {
        $shop = $this->shop($request);

        $open = $shop->jobs()->whereNotIn('status', ['collected', 'cancelled']);
        $openCount = (clone $open)->count();
        $dueToday = (clone $open)
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        return view('console.dashboard', [
            'props' => [
                'shell' => $this->shell($shop, $openCount),
                'heading' => [
                    'title' => 'Dashboard',
                    'sub' => now()->format('l, j F')
                        .' · '.$openCount.' '.($openCount === 1 ? 'job' : 'jobs').' in the queue'
                        .', '.$dueToday.' due today',
                ],
                'stats' => $this->stats($shop),
                'dueToday' => [
                    'count' => $dueToday,
                    'rows' => $this->dueToday($shop),
                ],
                'week' => $this->week($shop),
                'attention' => $this->attention($shop),
                'routes' => [
                    'dashboard' => route('console.dashboard'),
                    'export' => route('filament.shop.reports.export'),
                    'newJob' => route('shops.index'),
                ],
            ],
        ]);
    }

    /**
     * The shop this request operates on, or a 403.
     *
     * `User::canAccessPanel` is what admits a shop owner to the Filament panel,
     * so the console asks the same question rather than inventing a second rule
     * that could disagree with it.
     */
    private function shop(Request $request): Shop
    {
        $user = $request->user();
        $shop = $user?->currentShop();

        if ($user === null || ! $user->isShopOwner() || $shop === null || ! $shop->isActive()) {
            throw new AccessDeniedHttpException('The shop console is for an active shop.');
        }

        return $shop;
    }

    /**
     * The sidebar.
     *
     * The active item and the badge count are decided here: a template that
     * works out which nav item is "on" is a template that can get it wrong on a
     * route it has not seen.
     *
     * @return array<string, mixed>
     */
    private function shell(Shop $shop, int $openJobs): array
    {
        return [
            'brand' => $shop->name,
            'badge' => 'Shop',
            'user' => [
                'initials' => $this->initials(auth()->user()->name),
                'name' => auth()->user()->name,
                'role' => 'Shop owner',
            ],
            'groups' => [
                [
                    'label' => 'Operations',
                    'items' => [
                        $this->nav('Dashboard', route('console.dashboard'), 'grid', true),
                        $this->nav('Jobs', url('/shop/jobs'), 'inbox', false, $openJobs),
                        $this->nav('Production board', url('/shop/production-board'), 'board'),
                    ],
                ],
                [
                    'label' => 'Catalogue',
                    'items' => [
                        $this->nav('Price matrix', url('/shop/price-matrix'), 'table'),
                    ],
                ],
                [
                    'label' => 'Business',
                    'items' => [
                        $this->nav('Reports', url('/shop/reports'), 'chart'),
                        $this->nav('Billing', url('/shop/billing'), 'doc'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nav(string $label, string $url, string $icon, bool $active = false, ?int $badge = null): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge ?: null,
        ];
    }

    /**
     * The four numbers at the top.
     *
     * Each note is the honest qualifier: yesterday's takings, what is due, the
     * window the average covers, and how many of the collected jobs were on time.
     *
     * @return list<array<string, string>>
     */
    private function stats(Shop $shop): array
    {
        $today = $this->revenue($shop, Carbon::today(), Carbon::today()->endOfDay());
        $yesterday = $this->revenue($shop, Carbon::yesterday(), Carbon::yesterday()->endOfDay());

        $open = $shop->jobs()->whereNotIn('status', ['collected', 'cancelled']);
        $dueToday = (clone $open)
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        // Cancelled jobs never produced a peso, so they would only drag the
        // average down against work the shop actually did.
        $average = (int) round((float) $shop->jobs()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total'));

        $collected = $shop->jobs()->where('status', 'collected')->whereNotNull('collected_at');
        $collectedCount = (clone $collected)->count();
        $onTime = (clone $collected)->whereColumn('collected_at', '<=', 'due_at')->count();

        return [
            [
                'label' => 'Revenue today',
                'value' => Money::short($today),
                'note' => $this->revenueNote($today, $yesterday),
                // The arrow is the direction, so the note carries no sign of its
                // own — "↑ 18% vs yesterday", not "↑ +18% vs yesterday".
                'arrow' => $today >= $yesterday ? 'up' : 'down',
            ],
            [
                'label' => 'Open jobs',
                'value' => (string) (clone $open)->count(),
                'note' => $dueToday > 0 ? $dueToday.' due today' : 'nothing due today',
                'arrow' => null,
            ],
            [
                'label' => 'Avg. job value',
                'value' => Money::short($average),
                'note' => 'last 30 days, excluding cancelled',
                'arrow' => null,
            ],
            [
                'label' => 'On-time rate',
                'value' => $collectedCount > 0
                    ? (int) round($onTime / $collectedCount * 100).'%'
                    : '—',
                'note' => $collectedCount > 0
                    ? $onTime.' of '.$collectedCount.' collected on time'
                    : 'no jobs collected yet',
                'arrow' => null,
            ],
        ];
    }

    /** Money taken inside a window, in centavos. */
    private function revenue(Shop $shop, Carbon $from, Carbon $to): int
    {
        return (int) $shop->payments()
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->sum('payments.amount');
    }

    private function revenueNote(int $today, int $yesterday): string
    {
        if ($yesterday === 0) {
            return $today > 0 ? 'first takings today' : 'nothing taken yet today';
        }

        $change = (int) round(($today - $yesterday) / $yesterday * 100);

        return abs($change).'% vs yesterday';
    }

    /**
     * What has to leave the shop today.
     *
     * "Due" means `due_at` falls inside today's calendar day — not "overdue",
     * which is a separate problem and belongs in Needs attention.
     *
     * @return list<array<string, mixed>>
     */
    private function dueToday(Shop $shop): array
    {
        return $shop->jobs()
            ->with(['user', 'service'])
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->orderBy('due_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Job $job) => [
                'reference' => $job->reference,
                'customer' => $job->user?->name ?? '—',
                'cover' => $job->shop->coverGradient(),
                'title' => $job->titleLine(),
                'spec' => $job->specSummary(),
                'status' => $job->statusLabel(),
                'tone' => $this->statusTone($job->status),
                'total' => '₱'.number_format($job->total / 100),
                'url' => url('/shop/jobs/'.$job->id),
            ])
            ->all();
    }

    /**
     * Money taken over the last seven days, as bar heights.
     *
     * The percentage is rounded to a whole number here so the bars line up: a
     * 0.4% day would otherwise be a sliver the browser rounds inconsistently.
     *
     * @return array<string, mixed>
     */
    private function week(Shop $shop): array
    {
        $raw = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);

            $raw[] = [
                'label' => $day->format('D')[0],
                'total' => $this->revenue($shop, $day->copy()->startOfDay(), $day->copy()->endOfDay()),
                'current' => $i === 0,
            ];
        }

        $max = max(array_column($raw, 'total')) ?: 1;

        return [
            'total' => Money::short(array_sum(array_column($raw, 'total'))),
            'days' => array_map(fn (array $day) => [
                'label' => $day['label'],
                // Two percent floor: a day with takings should not be invisible
                // next to a record day, and a zero day still needs a baseline.
                'pct' => max(2, (int) round($day['total'] / $max * 100)),
                'current' => $day['current'],
            ], $raw),
        ];
    }

    /**
     * Jobs where the next move belongs to somebody else, longest wait first.
     *
     * @return array<string, mixed>
     */
    private function attention(Shop $shop): array
    {
        $jobs = $shop->jobs()
            ->with(['user', 'service', 'proofs'])
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->get();

        $rows = [];

        foreach ($jobs as $job) {
            $reason = $this->classify($job);

            if ($reason === null) {
                continue;
            }

            $rows[] = [
                'reference' => $job->reference,
                'issue' => $reason['issue'],
                'waiting' => $this->waited($reason['since']),
                'since' => $reason['since']->getTimestamp(),
                'url' => url('/shop/jobs/'.$job->id),
            ];
        }

        // Longest wait first: the oldest row is the one most likely to turn into
        // a complaint, and it is the one the owner should open first.
        usort($rows, fn (array $a, array $b) => $a['since'] <=> $b['since']);

        return [
            'count' => count($rows),
            'rows' => array_slice($rows, 0, self::LIMIT),
        ];
    }

    /**
     * Why this job needs a human, or null when it is simply in progress.
     *
     * @return array{issue: string, since: Carbon}|null
     */
    private function classify(Job $job): ?array
    {
        $now = now();

        if ($job->due_at && $job->due_at->lt($now) && in_array($job->status, ['approved', 'in_production', 'ready'], true)) {
            return ['issue' => 'Past its pickup date', 'since' => $job->due_at];
        }

        if ($job->status === 'awaiting_proof') {
            // The clock starts when the proof went out, not when the job was
            // created — a shop that sat on the artwork for two days should not
            // be told the customer is the one stalling.
            $sent = $job->latestProof()?->created_at ?? $job->updated_at;

            return $sent && $sent->lt($now->copy()->subHours(self::PROOF_HOURS))
                ? ['issue' => "Proof sent, customer hasn't approved", 'since' => $sent]
                : null;
        }

        if ($job->status === 'quoted' && $job->created_at->lt($now->copy()->subHours(self::QUOTE_HOURS))) {
            return ['issue' => "Quote sent, customer hasn't ordered", 'since' => $job->created_at];
        }

        if ($job->status === 'ready' && $job->updated_at->lt($now->copy()->subHours(self::PICKUP_HOURS))) {
            return ['issue' => 'Ready for pickup, not collected', 'since' => $job->updated_at];
        }

        return null;
    }

    /** "14 hrs", "2 days" — the same units the shop talks in, not a date. */
    private function waited(Carbon $since): string
    {
        $hours = (int) $since->diffInHours(now());

        if ($hours < 1) {
            return 'just now';
        }

        if ($hours < 24) {
            return $hours.' '.($hours === 1 ? 'hr' : 'hrs');
        }

        $days = (int) $since->diffInDays(now());

        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    /** The console's own palette for a status: green done, amber working, grey waiting. */
    private function statusTone(string $status): string
    {
        return match ($status) {
            'ready', 'collected' => 'g',
            'approved', 'in_production' => 'y',
            'cancelled' => 'r',
            default => 'n',
        };
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
