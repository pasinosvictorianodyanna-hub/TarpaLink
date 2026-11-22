<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling a job, and the refund that comes with it.
 *
 * The transition table already allows `cancelled` from the three pre-production
 * states, but nothing in the app ever asked for it — so the published policy
 * ("a full refund any time before you approve the proof") was a promise no
 * button kept.
 *
 * The refund is deliberately **not** inside `JobWorkflow`. That class answers one
 * question — may this job move — and a money decision bolted onto it would fire
 * for every caller, including the ones that only wanted to record a status. This
 * service is the single place that answers "and what happens to the money",
 * which is why both the console and the customer's own cancel go through it.
 *
 * Cancelling **after** the proof is approved does not refund automatically. The
 * material is already committed, so the policy says a refund there is the shop's
 * discretion and may be partial; that is a decision a person makes on the
 * payment, through `Refund`, not a side effect of a status change.
 */
final class Cancellation
{
    /** States where cancelling still owes the customer all of their money back. */
    private const REFUNDABLE = ['quoted', 'awaiting_proof'];

    /**
     * May the customer still walk away on their own?
     *
     * Only while the money is theirs to reclaim. Once the proof is approved the
     * material is committed and the policy hands the decision to the shop, so
     * the customer's own cancel stops being an option there — and that has to be
     * a check, not the absence of a button.
     */
    public static function customerMayCancel(Job $job): bool
    {
        return in_array($job->status, self::REFUNDABLE, true);
    }

    public function __construct(private readonly Refund $refund) {}

    /**
     * Cancel the job, and refund it in full when the policy says we must.
     *
     * Returns the payment it refunded, or null when no money moved — which is
     * not a failure. A job cancelled before it was ever paid, or one cancelled
     * after approval, both legitimately refund nothing.
     *
     * @throws ValidationException when the job cannot be cancelled at all
     */
    public function cancel(Job $job, ?User $by = null, ?string $reason = null): ?Payment
    {
        if (! JobWorkflow::canTransition($job->status, 'cancelled')) {
            throw ValidationException::withMessages([
                'status' => "A job that is \"{$job->statusLabel()}\" cannot be cancelled — the work has started.",
            ]);
        }

        $owesMoneyBack = in_array($job->status, self::REFUNDABLE, true);
        $payment = $owesMoneyBack ? $this->refundable($job) : null;

        return DB::transaction(function () use ($job, $by, $reason, $payment): ?Payment {
            JobWorkflow::transition($job, 'cancelled', $by, $reason);

            if ($payment === null) {
                return null;
            }

            return $this->refund->refundInFull($payment, $by, $reason);
        });
    }

    /** The job's settled payment, when there is one with money still on it. */
    private function refundable(Job $job): ?Payment
    {
        $payment = $job->payment;

        if ($payment === null || $payment->status !== 'paid') {
            return null;
        }

        return $payment->remainingRefundable() > 0 ? $payment : null;
    }
}
