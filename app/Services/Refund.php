<?php

namespace App\Services;

use App\Models\JobActivity;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money going back to the customer.
 *
 * `payments.status` has carried a `refunded` value since #11 and nothing ever
 * wrote it, so a job the shop could not produce had no way out of the ledger.
 * The published policy promises more than the code did: a full refund any time
 * before the proof is approved, and a partial one at the shop's discretion after
 * that.
 *
 * **The amount is the fact; the status is derived from it.** A status alone
 * cannot say that ₱400 of ₱1,000 came back, and partial is the normal case. So
 * `refunded_amount` accumulates and `status` flips to `refunded` only once the
 * whole payment has gone back.
 *
 * **The commission reverses; the processor's fee does not.** We did not earn a
 * commission on money the customer did not keep, so it comes off proportionally.
 * The processor keeps its fee on a refund, and by the decision on #81 that gap
 * is the shop's — which is why a fully refunded payment nets a small negative
 * rather than zero, and why the row must stay in the ledger instead of
 * disappearing from it.
 */
final class Refund
{
    /**
     * Send `$amount` back against a settled payment.
     *
     * `$amount` is the size of *this* refund, not the cumulative total — calling
     * this twice with ₱400 refunds ₱800.
     *
     * @throws ValidationException when the payment cannot take the refund
     */
    public function refund(Payment $payment, int $amount, ?User $by = null, ?string $reason = null): Payment
    {
        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'amount' => 'Only a settled payment can be refunded. This one is '.$payment->status.'.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'A refund has to be more than nothing.',
            ]);
        }

        $remaining = $payment->remainingRefundable();

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'That is more than is left to refund — '.Money::format($remaining).' remains on this payment.',
            ]);
        }

        return DB::transaction(function () use ($payment, $amount, $by, $reason): Payment {
            $refundedAfter = $payment->refunded_amount + $amount;

            // Computed as a cumulative target rather than a per-refund slice, so
            // three ₱333.33 refunds of a ₱1,000 payment reverse ₱50.00 in total
            // and not ₱49.98. `min()` guards the last one: rounding must never
            // reverse more commission than was ever charged.
            $commissionTarget = $payment->amount > 0
                ? (int) round($payment->commission * $refundedAfter / $payment->amount)
                : 0;

            $commissionTarget = min($commissionTarget, $payment->commission);

            $payment->forceFill([
                'refunded_amount' => $refundedAfter,
                'commission_refunded' => $commissionTarget,
                'refunded_at' => now(),
                'status' => $refundedAfter >= $payment->amount ? 'refunded' : 'paid',
            ])->save();

            // The money moved, so the job's history has to say so. Written in the
            // same transaction as the refund: a refund with no trail, or a trail
            // claiming a refund that never saved, are both worse than a failure.
            $payment->job?->activities()->create([
                'user_id' => $by?->id ?? auth()->id(),
                'kind' => JobActivity::NOTE,
                'from_status' => null,
                'to_status' => null,
                'note' => 'Refunded '.Money::format($amount).' of '.Money::format($payment->amount),
                'body' => $reason,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Refund whatever is left, which is the whole payment the first time.
     *
     * Cancellation goes through here rather than `refund($payment->amount)`,
     * because a second call would otherwise try to refund money that has already
     * gone back and trip the over-refund guard.
     */
    public function refundInFull(Payment $payment, ?User $by = null, ?string $reason = null): Payment
    {
        return $this->refund($payment, $payment->remainingRefundable(), $by, $reason);
    }
}
