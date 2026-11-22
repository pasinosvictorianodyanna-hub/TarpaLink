<?php

namespace App\Http\Controllers;

use App\Mail\ProofDecided;
use App\Models\Job;
use App\Models\Proof;
use App\Services\JobWorkflow;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The proof exchange between a shop and its customer.
 *
 * The file itself is on the private `proofs` disk — private because a proof is
 * artwork the customer paid for and the shop produced, and neither should be
 * reachable by guessing a filename. Every read goes through a route that checks
 * who is asking, which is also why the disk is never the public one.
 */
class ProofController extends Controller
{
    /** Inline view of one proof, for the customer who ordered or the shop that made it. */
    public function file(Request $request, Job $job, Proof $proof): StreamedResponse
    {
        $this->authorizeProof($request, $job, $proof);

        abort_unless($proof->exists(), 404);

        return Storage::disk(Proof::DISK)->response(
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

        $this->tellTheShop($job, $proof, approved: true, note: null);

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

        $this->tellTheShop($job, $proof, approved: false, note: $proof->note);

        return back()->with('status', 'Proof rejected. The shop will send a new version.');
    }

    /**
     * Tell the shop what the customer decided.
     *
     * A rejection is the more urgent case — the job is still sitting in
     * `awaiting_proof` and nothing moves until somebody at the shop opens it —
     * but both are worth an email, because the alternative is a shop finding out
     * by refreshing a queue.
     */
    private function tellTheShop(Job $job, Proof $proof, bool $approved, ?string $note): void
    {
        $shop = $job->shop;

        if ($shop === null) {
            return;
        }

        // Owners first: they are the ones who can answer for the job. Falling
        // back to any staff member keeps a shop with no owner row reachable
        // instead of silently notifying nobody.
        $recipient = $shop->owners()->first() ?? $shop->staff()->first();

        if ($recipient === null) {
            return;
        }

        Notifier::send($recipient->email, new ProofDecided(
            job: $job,
            approved: $approved,
            customerName: $job->user?->name ?? 'The customer',
            ownerName: $recipient->name,
            note: $note,
        ));
    }

    /** 404 rather than 403, so a guessed job reference confirms nothing. */
    private function authorizeProof(Request $request, Job $job, Proof $proof): void
    {
        abort_unless(Gate::allows('view', $job), 404);
        abort_unless($proof->job_id === $job->id, 404);
    }
}
