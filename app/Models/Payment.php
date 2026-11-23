<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    /**
     * Mirror the column defaults.
     *
     * Without these a `Payment::create([...])` that does not mention a refund
     * has no `refunded_amount` attribute at all, and `net()` — which is called
     * from views — would be reading null rather than zero.
     */
    protected $attributes = [
        'refunded_amount' => 0,
        'commission_refunded' => 0,
        'fee_is_estimate' => true,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'processing_fee' => 'integer',
            'fee_is_estimate' => 'boolean',
            'commission' => 'integer',
            'commission_refunded' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'failed_at' => 'datetime',
            'payout_date' => 'date',
            'attempts' => 'integer',
        ];
    }

    /**
     * The processor's fee we assume at checkout, before the provider tells us.
     *
     * The shop bears this, so the rate is configuration rather than two literals
     * in the checkout controller — a hardcoded cost is a pricing decision nobody
     * can find. The real fee depends on the payment method and arrives with the
     * settled payment; `fee_is_estimate` records which number a row carries.
     */
    public static function estimateFee(int $amount): int
    {
        // `env('PAYMONGO_FEE_RATE_BP', 250)` returns '' — not 250 — when the
        // deployment wrote `PAYMONGO_FEE_RATE_BP=` with nothing after it, and
        // `(int) ''` is 0. That would quietly turn a 2.5% fee into the flat
        // charge alone and credit the shop the difference. The guard lives here
        // rather than in the config because this is the only reader.
        $rateBp = (int) config('services.paymongo.fee_rate_bp') ?: 250;
        $flat = (int) config('services.paymongo.fee_flat') ?: 1500;

        return (int) round($amount * $rateBp / 10000) + $flat;
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * What the shop is owed on this payment, refunds included.
     *
     * `gross − commission − fee`, less everything handed back — with the
     * commission we gave up on the refunded part put back, because we did not
     * earn a commission on money the customer never kept.
     *
     * A full refund therefore does not reach zero. A ₱1,000 payment carrying
     * ₱50 of commission and ₱40 of processor fee refunded in full nets **−₱40**:
     * the customer got all ₱1,000 back, we kept no commission, and the processor
     * kept its fee, so the ₱40 gap has to be somebody's. It is the shop's, by the
     * decision on #81 — the fee was never the shop's money in the first place.
     * The negative lands in the ledger and is netted off the next payout rather
     * than being quietly absorbed by the platform.
     */
    public function net(): int
    {
        // Coerced rather than trusted: this runs in a view, and a model loaded
        // through a narrow `select()` may not carry the refund columns at all.
        return self::netFrom(
            $this->amount,
            (int) ($this->refunded_amount ?? 0),
            $this->commission,
            (int) ($this->commission_refunded ?? 0),
            $this->processing_fee,
        );
    }

    /**
     * The same subtraction, for callers that hold the numbers rather than a
     * model.
     *
     * The payout CSV streams flat rows through a cursor on purpose — it never
     * builds a Payment — and the reports screen subtracts aggregates rather than
     * rows, so neither can call `net()` without giving that up. This exists so
     * both can reach the one definition instead of writing the expression again
     * and drifting from it. Call it with named arguments; five positional
     * integers is a bug waiting to happen.
     */
    public static function netFrom(
        int $amount,
        int $refunded = 0,
        int $commission = 0,
        int $commissionRefunded = 0,
        int $fee = 0,
    ): int {
        return ($amount - $refunded) - ($commission - $commissionRefunded) - $fee;
    }

    /** How much of this payment can still go back to the customer. */
    public function remainingRefundable(): int
    {
        return max(0, $this->amount - $this->refunded_amount);
    }

    public function isFullyRefunded(): bool
    {
        return $this->refunded_amount > 0 && $this->refunded_amount >= $this->amount;
    }

    public function hasRefund(): bool
    {
        return $this->refunded_amount > 0;
    }

    public function isSettled(): bool
    {
        return $this->payout_status === 'paid';
    }

    /**
     * What the platform is out of pocket on a payment it has already released.
     *
     * A refund after release pays the customer out of the platform's own
     * balance, because the shop has already been handed its net. Nothing claws
     * it back, and `awaitingPayout` excludes a released row by design — so the
     * loss appeared in no total anywhere. A negative `net()` on a released row
     * *is* that debt; this names it so a screen can show it.
     *
     * A payment that was never released owes nothing: the refund comes out of
     * money the platform is still holding.
     */
    public function debtToPlatform(): int
    {
        if (! $this->isSettled()) {
            return 0;
        }

        return max(0, -$this->net());
    }

    /**
     * Released payments that took a refund, so a debt may exist.
     *
     * A scope rather than a column because `net()` is derived; this narrows the
     * set to rows worth computing it for.
     */
    public function scopeRefundedAfterRelease($query)
    {
        return $query->where('payments.payout_status', 'paid')
            ->where('payments.refunded_amount', '>', 0);
    }

    /**
     * Whether the money may be released to the shop yet.
     *
     * Payment is not delivery. The customer is charged at checkout, which is
     * before the proof, before the press, and long before anybody carries a
     * banner out of a shop — so `status = paid` says the platform is holding
     * money, not that the shop earned it. The published policy draws the line
     * in the same place: *"Payouts are released from the platform ledger after
     * the job is collected."*
     *
     * Until #94 nothing enforced that. A job cancelled from `approved` — which
     * refunded nothing under #82's decision, corrected in #108 — left a paid
     * payment sitting on the payouts screen, indistinguishable from a
     * deliverable one, and a bulk release paid the shop for work never printed.
     */
    public function mayBeReleased(): bool
    {
        return $this->releaseBlockedReason() === null;
    }

    /**
     * Why the money is not releasable, or null when it is.
     *
     * A sentence rather than a boolean because the screen has to say *why*: a
     * disabled button with no explanation is a support ticket, and "the job was
     * cancelled" and "the job is still on the press" are different answers that
     * lead to different actions.
     */
    public function releaseBlockedReason(): ?string
    {
        if ($this->isSettled()) {
            return 'Already released.';
        }

        if ($this->isFullyRefunded()) {
            return 'Fully refunded — there is nothing left to pay out.';
        }

        if ($this->status !== 'paid') {
            return 'The payment has not settled yet.';
        }

        $job = $this->job;

        if ($job === null) {
            return 'No job is attached to this payment.';
        }

        return match ($job->status) {
            'collected' => null,
            'cancelled' => 'The job was cancelled, so there is nothing to pay for.',
            default => 'The job is still "'.strtolower($job->statusLabel()).'" — payouts release once it is collected.',
        };
    }

    /**
     * Money we hold for the shop but have not released yet.
     *
     * Only paid payments qualify: a pending intent is not revenue, and a failed
     * one is not owed to anybody.
     *
     * **This is not the payable set.** A captured payment for a job that has not
     * been collected is still awaiting payout — it is just not releasable yet,
     * which is `mayBeReleased()`. Keeping the two apart is the point of #94.
     *
     * Columns are qualified because this scope also runs through
     * `$shop->payments()`, a hasManyThrough that joins `jobs` — and `jobs` has a
     * `status` column of its own, so an unqualified one is ambiguous.
     */
    public function scopeAwaitingPayout($query)
    {
        return $query->where('payments.status', 'paid')->where('payments.payout_status', 'pending');
    }

    public function scopeSettled($query)
    {
        return $query->where('payments.status', 'paid');
    }

    /**
     * Money that moved, including money that moved back.
     *
     * The ledger has to include a refunded payment. Its net is negative — the
     * fee we cannot recover — and dropping the row would make the shop's loss
     * vanish instead of being netted off the next payout.
     */
    public function scopeMoved($query)
    {
        return $query->whereIn('payments.status', ['paid', 'refunded']);
    }

    /**
     * Payments whose job has been collected.
     *
     * The line between money we are holding and money we have earned. The
     * published pricing says commission is charged "per completed job", so
     * revenue is recognised here and not at capture — a payment on a job still
     * on the press, or on one that was cancelled before it was printed, is money
     * in transit.
     *
     * One definition on purpose: the dashboard and the stats widget both read
     * it, and two hand-written `whereHas` clauses would drift.
     */
    public function scopeOnCollectedJobs($query)
    {
        return $query->whereHas('job', fn ($job) => $job->where('status', 'collected'));
    }

    /** Payments that exhausted their webhook retries and need a human. */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('payments.status', 'failed')
                ->orWhere(fn ($q2) => $q2->where('payments.status', 'pending')->where('payments.attempts', '>=', 3));
        });
    }
}
