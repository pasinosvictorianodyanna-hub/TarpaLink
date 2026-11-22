<?php

namespace App\Http\Controllers\Console;

use App\Models\Job;
use App\Models\Proof;
use App\Services\JobWorkflow;
use App\Services\ProofExchange;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The queue, and one job inside it.
 *
 * Everything a shop owner does to a job during the day: read it, send a proof,
 * move it along. Both writes go through JobWorkflow and ProofExchange — the
 * same two classes the panel used — so a status that is illegal here is illegal
 * everywhere, and the board cannot accept a move this screen refuses.
 */
class JobController extends ConsoleController
{
    /** Rows per page. Enough to fill a screen, few enough that the page is quick. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $shop = $this->shop();

        $status = $request->string('status')->toString();
        $term = trim($request->string('q')->toString());

        // An unknown status is ignored rather than 404'd: a bookmarked filter
        // should degrade to "all", not to an error page.
        $status = array_key_exists($status, Job::STATUS_LABELS) ? $status : '';

        $jobs = $shop->jobs()
            ->with(['user', 'service'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('reference', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%'));
                });
            })
            // `id` breaks ties: the seeder creates several jobs in the same
            // second, and without it the order changes between page loads.
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = $shop->jobs()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->page(
            'jobs',
            [
                'title' => 'Jobs',
                'sub' => $this->plural($counts->sum(), 'job').' all time · '
                    .$this->plural((int) ($counts->sum() - ($counts['collected'] ?? 0) - ($counts['cancelled'] ?? 0)), 'job').' still open',
            ],
            'console.jobs.index',
            [
                'jobs' => $jobs,
                'counts' => $counts,
                'status' => $status,
                'term' => $term,
            ],
            [
                ['label' => 'Production board', 'url' => route('console.board')],
            ],
        );
    }

    public function show(Job $job): View
    {
        $shop = $this->shop();

        // Route-model binding resolves by reference across every shop, so the
        // tenancy check is explicit: a reference from another shop must 404,
        // not render.
        abort_unless($job->shop_id === $shop->id, 404);

        $job->load(['user', 'service', 'proofs', 'payment', 'activities.user']);

        return $this->page(
            'jobs',
            [
                'title' => $job->reference,
                'sub' => $job->titleLine().' · '.$job->statusLabel(),
            ],
            'console.jobs.show',
            [
                'job' => $job,
                'allowed' => JobWorkflow::allowedFrom($job->status),
                'canProof' => in_array($job->status, ['quoted', 'awaiting_proof'], true),
            ],
            [
                ['label' => 'All jobs', 'url' => route('console.jobs')],
            ],
        );
    }

    /**
     * Send the artwork for the customer to approve.
     *
     * Versioned rather than overwritten: rejecting a proof must not erase what
     * the customer saw, and the last row is what `Job::latestProof()` returns.
     * The file goes on the private disk and is served through a checked route.
     */
    public function sendProof(Request $request, Job $job): RedirectResponse
    {
        abort_unless($job->shop_id === $this->shop()->id, 404);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:10240'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('file')->store('proofs', Proof::DISK);

        app(ProofExchange::class)->send($job, $path, $data['note'] ?? null, $this->user());

        return redirect()
            ->route('console.jobs.show', $job)
            ->with('status', 'Proof sent to '.($job->user?->name ?? 'the customer').'.');
    }

    /** Move the job along. The workflow refuses anything the board would refuse. */
    public function advance(Request $request, Job $job): RedirectResponse
    {
        abort_unless($job->shop_id === $this->shop()->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        JobWorkflow::transition($job, $data['status'], $this->user());

        return redirect()
            ->route('console.jobs.show', $job)
            ->with('status', $job->reference.' is now '.$job->fresh()->statusLabel().'.');
    }
}
