<?php

namespace App\Http\Controllers\Console;

use App\Models\Job;
use App\Models\Shop;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * What the shop has to know before it opens: today's takings, the queue, the
 * week, and the jobs where the next move belongs to somebody else.
 */
class DashboardController extends ConsoleController
{
    /** Hours of silence before a sent proof counts as stuck. */
    private const PROOF_HOURS = 6;

    /** Hours before an unconfirmed quote counts as stuck. */
    private const QUOTE_HOURS = 24;

    /** Hours a finished job may sit on the shelf before it is chased. */
    private const PICKUP_HOURS = 48;

    /** How many rows a card shows before it stops being a glance. */
    private const LIMIT = 6;

    public function __invoke(Request $request): View
    {
        $shop = $this->shop();

        $open = $shop->jobs()->whereNotIn('status', ['collected', 'cancelled']);
        $openCount = (clone $open)->count();
        $dueToday = (clone $open)
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        return $this->page(
            'dashboard',
            [
                'title' => 'Dashboard',
                'sub' => now()->format('l, j F')
                    .' · '.$this->plural($openCount, 'job').' in the queue'
                    .', '.$dueToday.' due today',
            ],
            'console.dashboard',
            [
                'stats' => $this->stats($shop),
                'dueToday' => [
                    'count' => $dueToday,
                    'rows' => $this->dueToday($shop),
                ],
                'week' => $this->week($shop),
                'attention' => $this->attention($shop),
            ],
            [
                ['label' => 'Export CSV', 'url' => route('console.reports.export')],
                ['label' => 'Production board', 'url' => route('console.board'), 'style' => 'primary'],
            ],
        );
    }

    /**
     * The four numbers at the top.
     *
     * Each note is the honest qualifier: yesterday's takings, what is due, the
     * window the average covers, and how many of the collected jobs were on time.
     *
     * @return list<array<string, string|null>>
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
                'spec' => ($job->service?->unit ?? 'pc') === 'sqft'
                    ? $job->specSummary()
                    : ($job->sizeLabel().(str_contains($job->specSummary(), ' · ') ? ' · '.substr($job->specSummary(), strpos($job->specSummary(), ' · ') + 3) : '')),
                'status' => $job->statusLabel(),
                'tone' => $this->statusTone($job->status),
                'total' => '₱'.number_format($job->total / 100),
                'url' => route('console.jobs.show', $job),
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
                'url' => route('console.jobs.show', $job),
                // The row exists to be acted on, so the chase is one click from
                // the list rather than a trip through the job page and back.
                'nudge' => route('console.jobs.nudge', $job),
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

        if ($job->status === 'quoted') {
            // `quoted` means the order is in and the money is not — there is no
            // "ordering" left for the customer to do, so saying they "haven't
            // ordered" describes a step that does not exist. Who the shop is
            // waiting on depends on the payment, and the two cases deserve
            // different words: unpaid is the customer's move, paid-with-no-proof
            // is the shop's own delay and is the more urgent of the two.
            if ($job->isPaid()) {
                return $job->created_at->lt($now->copy()->subHours(self::PROOF_HOURS))
                    ? ['issue' => 'Paid, proof not sent yet', 'since' => $job->created_at]
                    : null;
            }

            return $job->created_at->lt($now->copy()->subHours(self::QUOTE_HOURS))
                ? ['issue' => 'Unpaid, payment not confirmed', 'since' => $job->created_at]
                : null;
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
}
