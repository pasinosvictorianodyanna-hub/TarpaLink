<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Proof;
use App\Services\JobWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The proof exchange between a shop and its customer.
 *
 * The file itself is on the private local disk. Every read goes through a route
 * that checks who is asking — a proof is artwork the customer paid for and the
 * shop produced, and neither should be reachable by guessing a filename.
 */
class ProofController extends Controller
{
    /** Inline view of one proof, for the customer who ordered or the shop that made it. */
    public function file(Request $request, Job $job, Proof $proof): StreamedResponse
    {
        $this->authorizeProof($request, $job, $proof);

        return Storage::disk('local')->response(
            $proof->file_path,
            'proof-v'.$proof->version.'-'.$job->reference.'.'.pathinfo($proof->file_path, PATHINFO_EXTENSION),
        );
    }

    public function approve(Request $request, Job $job, Proof $proof): RedirectResponse
    {
        $this->authorizeProof($request, $job, $proof);

        // Only the customer can approve, and only while the proof is outstanding.
        abort_unless($request->user()?->isCustomer(), 403);
        abort_unless($proof->status === 'sent', 422, 'This proof has already been decided.');

        if ($job->status !== 'awaiting_proof') {
            return back()->with('status', 'This job is no longer waiting for a proof decision.');
        }

        $proof->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        JobWorkflow::transition(
            $job,
            'approved',
            $request->user(),
            'Proof v'.$proof->version.' approved by the customer',
        );

        return back()->with('status', 'Proof approved. The shop can put your job on the press.');
    }

    public function reject(Request $request, Job $job, Proof $proof): RedirectResponse
    {
        $this->authorizeProof($request, $job, $proof);

        abort_unless($request->user()?->isCustomer(), 403);
        abort_unless($proof->status === 'sent', 422, 'This proof has already been decided.');

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // The job stays in `awaiting_proof` so the shop knows it owes a new
        // version. The rejected proof remains in history for both sides.
        $proof->update([
            'status' => 'rejected',
            'note' => $data['note'] ?: 'The customer asked for changes.',
        ]);

        return back()->with('status', 'Proof rejected. The shop will send a new version.');
    }

    /** 404 rather than 403, so a guessed job reference confirms nothing. */
    private function authorizeProof(Request $request, Job $job, Proof $proof): void
    {
        abort_unless(Gate::allows('view', $job), 404);
        abort_unless($proof->job_id === $job->id, 404);
    }
}
