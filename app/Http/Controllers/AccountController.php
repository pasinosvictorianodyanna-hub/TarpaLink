<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountController extends Controller
{
    /** Statuses that mean "the shop is working on it", not "it is finished". */
    private const ACTIVE = ['quoted', 'awaiting_proof', 'approved', 'in_production'];

    /** Statuses whose row is history, so the card recedes. */
    private const DONE = ['collected', 'cancelled'];

    public function index(Request $request): View
    {
        // One query, filtered in memory. A customer has a handful of jobs, not a
        // ledger, and deriving the tab counts and the rows from the same
        // collection is what keeps the numbers and the list from disagreeing.
        $all = $request->user()
            ->jobs()
            ->with(['shop', 'service', 'proofs', 'payment'])
            ->latest()
            ->get();

        $counts = [
            'all' => $all->count(),
            'active' => $all->whereIn('status', self::ACTIVE)->count(),
            'ready' => $all->where('status', 'ready')->count(),
            'done' => $all->whereIn('status', self::DONE)->count(),
        ];

        $filter = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q'));

        $jobs = $all->filter(fn (Job $job) => match ($filter) {
            'active' => in_array($job->status, self::ACTIVE, true),
            'ready' => $job->status === 'ready',
            'done' => in_array($job->status, self::DONE, true),
            default => true,
        });

        if ($q !== '') {
            $needle = mb_strtolower($q);

            $jobs = $jobs->filter(fn (Job $job) => str_contains(mb_strtolower($job->titleLine()), $needle)
                || str_contains(mb_strtolower($job->shop->name), $needle)
                || str_contains(mb_strtolower($job->reference), $needle));
        }

        $needsProof = $all->firstWhere('status', 'awaiting_proof');

        return view('account', [
            'props' => [
                'summary' => $this->summary($counts['active'], $counts['all'], $needsProof !== null),
                'kpis' => $this->kpis($counts, $all, $needsProof),
                'alert' => $this->alert($needsProof),
                'tabs' => $this->tabs($counts, $filter, $q),
                'search' => ['q' => $q, 'filter' => $filter],
                'jobs' => $jobs->map(fn (Job $job) => $this->row($job))->values()->all(),
                'empty' => $jobs->isEmpty() ? $this->emptyState($q) : null,
                'account' => $this->account($request->user()),
                'routes' => [
                    'shops' => route('shops.index'),
                    'account' => route('account'),
                ],
            ],
        ]);
    }

    /**
     * The three boxes above the list.
     *
     * Each note is the one fact worth adding to the number: how many are actually
     * being printed, which shop has something waiting, and whether the customer
     * owes the platform a decision.
     *
     * @param  array<string, int>  $counts
     * @param  Collection<int, Job>  $all
     * @return list<array<string, string>>
     */
    private function kpis(array $counts, Collection $all, ?Job $needsProof): array
    {
        $inProduction = $all->where('status', 'in_production')->count();
        $readyShop = $all->firstWhere('status', 'ready')?->shop->name;

        return [
            [
                'label' => 'Active jobs',
                'value' => (string) $counts['active'],
                'note' => $inProduction.' in production',
                'tone' => 'muted',
            ],
            [
                'label' => 'Ready to collect',
                'value' => (string) $counts['ready'],
                'note' => $readyShop ?? 'Nothing at the counter',
                'tone' => 'muted',
            ],
            [
                'label' => 'Needs your action',
                'value' => $needsProof === null ? '0' : '1',
                'note' => $needsProof === null ? 'Nothing waiting on you' : 'Proof approval',
                'tone' => $needsProof === null ? 'muted' : 'warn',
            ],
        ];
    }

    /**
     * The magenta bar, or null.
     *
     * Null is what removes the bar from the page — the component does not decide
     * whether the customer has something to approve.
     *
     * @return array<string, string>|null
     */
    private function alert(?Job $job): ?array
    {
        if ($job === null) {
            return null;
        }

        return [
            'title' => 'Proof ready for approval — #'.$job->reference,
            'meta' => $job->shop->name.' · '.$job->titleLine()
                .' — approve within 12 hrs or the job is paused.',
            'label' => 'Review proof',
            'url' => route('jobs.show', $job),
        ];
    }

    /**
     * The four tabs, with their counts and their destinations.
     *
     * The URL is built here so the component never assembles a query string: an
     * empty `?status=` is not the same URL as no query at all, and only one of
     * them leaves the tab strip tidy.
     *
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function tabs(array $counts, string $filter, string $q): array
    {
        $tabs = [
            ['all', 'All', $counts['all']],
            ['active', 'Active', $counts['active']],
            ['ready', 'Ready', $counts['ready']],
            ['done', 'Completed', $counts['done']],
        ];

        return array_map(fn (array $tab) => [
            'key' => $tab[0],
            'label' => $tab[1],
            'count' => $tab[2],
            'on' => $filter === $tab[0],
            'url' => route('account', array_filter([
                'status' => $tab[0] === 'all' ? null : $tab[0],
                'q' => $q !== '' ? $q : null,
            ])),
        ], $tabs);
    }

    /**
     * One job row, flattened to what the row renders.
     *
     * The button is the interesting part: which one appears is a rule about the
     * job's state, and a rule that lives in the template is a rule the browser can
     * get wrong.
     *
     * @return array<string, mixed>
     */
    private function row(Job $job): array
    {
        return [
            'title' => $job->titleLine(),
            'status' => $job->statusLabel(),
            'tone' => $job->statusTone(),
            'shop' => $job->shop->name,
            'meta' => $job->rowMeta(),
            'total' => '₱'.number_format($job->total / 100),
            'next' => $job->nextStepLine(),
            'cover' => $job->shop->coverGradient(),
            'dim' => in_array($job->status, self::DONE, true),
            'action' => $this->action($job),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function action(Job $job): array
    {
        if ($job->status === 'awaiting_proof') {
            return ['label' => 'Review proof', 'style' => 'magenta', 'url' => route('jobs.show', $job)];
        }

        if ($job->status === 'collected') {
            return [
                'label' => 'Reorder',
                'style' => 'ghost',
                'url' => route('shops.builder', [$job->shop, 'service_id' => $job->spec['service_id'] ?? null]),
            ];
        }

        return ['label' => 'View', 'style' => 'ghost', 'url' => route('jobs.show', $job)];
    }

    /**
     * @return array<string, string>
     */
    private function emptyState(string $q): array
    {
        if ($q !== '') {
            return [
                'title' => 'Nothing matches “'.$q.'”.',
                'body' => 'Try a shop name, a service or a job number.',
            ];
        }

        return [
            'title' => 'No jobs here yet.',
            'body' => 'Find a shop, set your size, and see the exact price before you order.',
        ];
    }

    /**
     * The account strip at the foot of the list.
     *
     * @return array<string, string>
     */
    private function account(User $user): array
    {
        return [
            'initials' => $this->initials($user->name),
            'name' => $user->name,
            'contact' => collect([$user->email, $user->phone])->filter()->implode(' · '),
            'city' => $user->city ?: 'Metro Manila',
            'url' => route('profile.edit'),
        ];
    }

    /** "Jomar Mendoza" -> "JM". Two letters, like the design's avatar. */
    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /** "2 jobs in progress, 1 waiting for your proof approval." */
    private function summary(int $active, int $total, bool $needsProof): string
    {
        if ($total === 0) {
            return 'Find a shop, set your size, and see the exact price before you order.';
        }

        $parts = [];

        if ($active > 0) {
            $parts[] = $active.' job'.($active === 1 ? '' : 's').' in progress';
        }

        if ($needsProof) {
            $parts[] = '1 waiting for your proof approval';
        }

        return $parts === []
            ? 'Everything you have ordered, across every shop.'
            : ucfirst(implode(', ', $parts)).'.';
    }
}
