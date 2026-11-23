<?php

namespace App\Http\Controllers;

use App\Models\Job;
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
            ->with(['shop', 'service', 'proofs', 'payment', 'artworks'])
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

        // Two different things can be waiting on a customer, and both are the
        // reason a job is not moving: a proof to approve, and a design file the
        // shop has never received. The old code counted only the first, and
        // counted it as a hard-coded "1" — three proofs waiting read as one.
        //
        // The design count is a count, not a yes/no. It was `$needsArtwork ===
        // null ? 0 : 1`, so two unpaid orders both missing their file read as
        // one while the list beneath showed two "Send design" buttons.
        $awaitingProof = $all->where('status', 'awaiting_proof');
        $needsProof = $awaitingProof->first();

        $awaitingDesign = $all
            ->where('status', 'quoted')
            ->filter(fn (Job $job): bool => $job->artworks->isEmpty());

        $needsArtwork = $awaitingDesign->first();
        $designs = $awaitingDesign->count();

        return view('account', [
            'props' => [
                'summary' => $this->summary($counts['active'], $counts['all'], $awaitingProof->count(), $designs),
                'kpis' => $this->kpis($counts, $all, $awaitingProof->count(), $designs),
                'alert' => $this->alert($needsProof, $needsArtwork),
                'tabs' => $this->tabs($counts, $filter, $q),
                'search' => ['q' => $q, 'filter' => $filter],
                'jobs' => $jobs->map(fn (Job $job) => $this->row($job))->values()->all(),
                'empty' => $jobs->isEmpty() ? $this->emptyState($q, $filter, $counts) : null,
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
    private function kpis(array $counts, Collection $all, int $proofs, int $designs): array
    {
        $inProduction = $all->where('status', 'in_production')->count();
        $readyShop = $all->firstWhere('status', 'ready')?->shop->name;

        // A number with no explanation sends the customer hunting. Say which
        // thing is waiting, and how many of each.
        $actions = $proofs + $designs;

        $note = match (true) {
            $proofs > 0 && $designs > 0 => $proofs.' proof'.($proofs === 1 ? '' : 's')
                .' · '.$designs.' design'.($designs === 1 ? '' : 's').' to send',
            $proofs > 0 => $proofs.' proof'.($proofs === 1 ? '' : 's').' to approve',
            $designs > 1 => $designs.' designs to send',
            $designs === 1 => 'Send your design',
            default => 'Nothing waiting on you',
        };

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
                'value' => (string) $actions,
                'note' => $note,
                'tone' => $actions === 0 ? 'muted' : 'warn',
            ],
        ];
    }

    /**
     * The magenta bar, or null.
     *
     * Null is what removes the bar from the page — the component does not decide
     * whether the customer has something to do. A proof outranks a missing
     * design: the shop is already holding the job, and one click finishes it.
     *
     * @return array<string, string>|null
     */
    private function alert(?Job $proof, ?Job $needsArtwork): ?array
    {
        if ($proof !== null) {
            return [
                'title' => 'Proof ready for approval — #'.$proof->reference,
                // The old copy ended "approve within 12 hrs or the job is
                // paused". Nothing pauses a job on a timer, so the sentence was
                // a deadline the system would not enforce.
                'meta' => $proof->shop->name.' · '.$proof->titleLine()
                    .'. Nothing goes to press until you approve.',
                'label' => 'Review proof',
                'url' => route('jobs.show', $proof),
            ];
        }

        if ($needsArtwork !== null) {
            return [
                'title' => 'Send your design — #'.$needsArtwork->reference,
                'meta' => $needsArtwork->shop->name.' · '.$needsArtwork->titleLine()
                    .'. They cannot start until the file arrives.',
                'label' => 'Send design',
                'url' => route('jobs.show', $needsArtwork),
            ];
        }

        return null;
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

        // A job with no design file cannot move, so the row's button is the
        // thing that unblocks it rather than a link to a page that only says so.
        if ($job->status === 'quoted' && $job->artworks->isEmpty()) {
            return ['label' => 'Send design', 'style' => 'magenta', 'url' => route('jobs.show', $job)];
        }

        if ($job->status === 'collected') {
            // The size and quantity travel with the link. They used to be left
            // behind, so "Reorder" opened the builder at the shop's default size
            // and the customer silently got a different quote. The finishing
            // options are not carried — they are re-picked on the builder, where
            // the price updates as they are added.
            $spec = $job->spec ?? [];

            return [
                'label' => 'Order again',
                'style' => 'ghost',
                'url' => route('shops.builder', array_filter([
                    'shop' => $job->shop,
                    'service_id' => $spec['service_id'] ?? $job->service_id,
                    'width_in' => $spec['width_in'] ?? null,
                    'height_in' => $spec['height_in'] ?? null,
                    'qty' => $spec['qty'] ?? null,
                ], fn ($value) => $value !== null)),
            ];
        }

        return ['label' => 'View', 'style' => 'ghost', 'url' => route('jobs.show', $job)];
    }

    /**
     * What an empty list says.
     *
     * Two different emptinesses: nothing matched a search, or nothing is in the
     * tab you are looking at. The old version answered both with "No jobs here
     * yet — find a shop", which told a customer with fourteen jobs that they had
     * none the moment they clicked an empty tab.
     *
     * @param  array<string, int>  $counts
     * @return array<string, string>
     */
    private function emptyState(string $q, string $filter, array $counts): array
    {
        if ($q !== '') {
            return [
                'title' => 'Nothing matches “'.$q.'”.',
                'body' => 'Try a shop name, a service or a job number.',
                'cta' => ['label' => 'Clear the search', 'url' => route('account')],
            ];
        }

        if ($filter !== 'all' && $counts['all'] > 0) {
            return [
                'title' => 'Nothing in this tab.',
                'body' => 'You have '.$counts['all'].' job'.($counts['all'] === 1 ? '' : 's').' in total.',
                // "Browse shops" was the only button this block ever had, which
                // is the wrong advice for somebody who already has orders — they
                // need the tab they came from, not the directory.
                'cta' => ['label' => 'Show all jobs', 'url' => route('account')],
            ];
        }

        return [
            'title' => 'No jobs here yet.',
            'body' => 'Find a shop, set your size, and see the exact price before you order.',
            'cta' => ['label' => 'Browse shops', 'url' => route('shops.index')],
        ];
    }

    /** "2 jobs in progress, 1 waiting for your proof approval, 2 waiting for your design." */
    private function summary(int $active, int $total, int $proofs, int $designs): string
    {
        if ($total === 0) {
            return 'Find a shop, set your size, and see the exact price before you order.';
        }

        $parts = [];

        if ($active > 0) {
            $parts[] = $active.' job'.($active === 1 ? '' : 's').' in progress';
        }

        if ($proofs > 0) {
            $parts[] = $proofs.' waiting for your proof approval';
        }

        if ($designs > 0) {
            $parts[] = $designs.' waiting for your design';
        }

        return $parts === []
            ? 'Everything you have ordered, across every shop.'
            : ucfirst(implode(', ', $parts)).'.';
    }
}
