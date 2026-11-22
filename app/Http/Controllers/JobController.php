<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\Cancellation;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function show(Job $job): View
    {
        // A job carries the customer's name, phone and payment, and the reference
        // is `TL-` plus five digits — guessable. 404 rather than 403 so the
        // response does not confirm that the job exists at all.
        abort_unless(Gate::allows('view', $job), 404);

        $job->load(['shop', 'service', 'lines', 'proofs', 'payment']);

        return view('jobs.show', ['job' => $job]);
    }

    /**
     * The customer walks away from an order that has not gone to press.
     *
     * The published policy has promised this since the site was written — "a full
     * refund any time before you approve the proof" — and nothing implemented it,
     * so a customer who changed their mind had to email the shop and hope.
     */
    public function cancel(Request $request, Job $job): RedirectResponse
    {
        abort_unless(Gate::allows('view', $job), 404);

        // Seeing a job and cancelling it are different rights. A shop may look at
        // the job it is printing; only the customer who ordered it can walk away
        // from it.
        abort_unless($request->user()?->id === $job->user_id, 403);

        // Past the proof the material is committed and the policy hands the
        // decision to the shop, so this route is closed even though the button
        // that leads here is already hidden.
        abort_unless(Cancellation::customerMayCancel($job), 403, 'This job has gone to press. Contact the shop to cancel it.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $refunded = app(Cancellation::class)->cancel($job, $request->user(), $data['reason'] ?? null);

        return redirect()
            ->route('jobs.show', $job)
            ->with('status', $refunded === null
                ? 'Order cancelled. Nothing had been paid, so there is nothing to refund.'
                : 'Order cancelled and '.Money::format($refunded->refunded_amount).' refunded to you.');
    }

    /** The receipt a customer can print or keep — the same view the shop sees. */
    public function receipt(Job $job): View
    {
        abort_unless(Gate::allows('view', $job), 404);

        $job->load(['shop', 'service', 'lines', 'payment', 'user']);

        return view('jobs.receipt', ['job' => $job]);
    }
}
