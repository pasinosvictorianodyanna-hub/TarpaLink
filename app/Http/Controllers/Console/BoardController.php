<?php

namespace App\Http\Controllers\Console;

use App\Models\Job;
use App\Services\JobWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Kanban of the shop's active jobs.
 *
 * Dropping a card calls the same JobWorkflow the job screen uses, so an illegal
 * drop is refused here exactly as it is refused there — the board is never the
 * only thing standing between a shop and a corrupt status.
 */
class BoardController extends ConsoleController
{
    public function index(): View
    {
        $shop = $this->shop();

        $jobs = $shop->jobs()
            ->with(['user', 'service'])
            // Whether the customer's file has arrived, so a card can say so
            // without a click into the job.
            ->withCount('artworks')
            ->whereIn('status', JobWorkflow::BOARD)
            ->orderBy('due_at')
            ->get()
            ->groupBy('status');

        $columns = collect(JobWorkflow::BOARD)
            ->map(fn (string $status): array => [
                'key' => $status,
                'label' => Job::STATUS_LABELS[$status] ?? $status,
                'jobs' => $jobs->get($status, collect()),
            ])
            ->all();

        return $this->page(
            'board',
            [
                'title' => 'Production board',
                'sub' => $this->plural(collect($columns)->sum(fn (array $column) => $column['jobs']->count()), 'job').' on the board',
            ],
            'console.board',
            ['columns' => $columns],
            [['label' => 'All jobs', 'url' => route('console.jobs')]],
        );
    }

    public function move(Request $request, Job $job): RedirectResponse
    {
        abort_unless($job->shop_id === $this->shop()->id, 404);

        $data = $request->validate(['status' => ['required', 'string']]);

        try {
            JobWorkflow::transition($job, $data['status'], $this->user());
        } catch (ValidationException $e) {
            // The layout renders `$errors`, so an illegal drop comes back as a
            // banner on the board rather than a 500.
            return back()->withErrors($e->errors());
        }

        return back()->with('status', $job->reference.' moved to '.$job->fresh()->statusLabel().'.');
    }
}
