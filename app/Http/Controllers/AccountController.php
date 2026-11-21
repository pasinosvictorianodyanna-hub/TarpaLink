<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** Statuses that mean "the shop is working on it", not "it is finished". */
    private const ACTIVE = ['quoted', 'awaiting_proof', 'approved', 'in_production'];

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
            'done' => $all->whereIn('status', ['collected', 'cancelled'])->count(),
        ];

        $filter = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q'));

        $jobs = $all->filter(fn (Job $job) => match ($filter) {
            'active' => in_array($job->status, self::ACTIVE, true),
            'ready' => $job->status === 'ready',
            'done' => in_array($job->status, ['collected', 'cancelled'], true),
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
            'jobs' => $jobs->values(),
            'counts' => $counts,
            'filter' => $filter,
            'q' => $q,
            'needsProof' => $needsProof,
            'inProduction' => $all->where('status', 'in_production')->count(),
            'readyShop' => $all->firstWhere('status', 'ready')?->shop->name,
            'summary' => $this->summary($counts['active'], $counts['all'], $needsProof !== null),
        ]);
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
