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
 * **Cancelling always returns the money that has not been returned yet**, at
 * every state that can be cancelled. That is not the same rule as *who may
 * cancel*: FR-105 still stops the customer walking away once the proof is
 * approved, because at that point the decision is the shop's. But if the job is
 * cancelled — by either party — the platform cannot go on holding a payment for
 * work that will not be produced.
 *
 * Before this, cancelling from `approved` refunded nothing and left the payment
 * `paid`, so the customer's money sat with the platform indefinitely: not
 * payable to the shop (`mayBeReleased` refuses a cancelled job) and not
 * refundable by the customer (the route is closed). Only an administrator
 * stumbling across the row could resolve it.
 */
final class Cancellation
{
    /**
     * States where the **customer** may walk away on their own.
     *
     * Not the same question as "does the money go back". This gate is about who
     * is allowed to decide (FR-105); the refund below is about what happens to
     * money that is already captured, whoever decides.
     */
    private const CUSTOMER_MAY_CANCEL = ['quoted', 'awaiting_proof'];

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
        return in_array($job->status, self::CUSTOMER_MAY_CANCEL, true);
    }

    public function __construct(private readonly Refund $refund) {}

    /**
     * Cancel the job, and refund it in full when the policy says we must.
     *
     * Returns the payment it refunded, or null when no money moved — which is
     * not a failure. A job cancelled before it was ever paid legitimately
     * refunds nothing; one cancelled after approval refunds whatever is left.
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

        // Whoever decided, and whatever the policy says about *whether* the
        // customer may ask, captured money cannot simply stay with the platform.
        // A job cancelled from `approved` used to leave the payment `paid` and
        // the customer refunded nothing — money that was neither the shop's (the
        // job will not be printed) nor ours. Refunding the remainder is the only
        // disposition that leaves nobody holding funds for work that will not
        // happen; a shop that did real work keeps it by refunding partially, or
        // not cancelling at all.
        $payment = $this->refundable($job);

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
