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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'processing_fee' => 'integer',
            'commission' => 'integer',
            'commission_refunded' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'failed_at' => 'datetime',
            'payout_date' => 'date',
            'attempts' => 'integer',
        ];
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
     * Money we hold for the shop but have not released yet.
     *
     * Only paid payments qualify: a pending intent is not revenue, and a failed
     * one is not owed to anybody.
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

    /** Payments that exhausted their webhook retries and need a human. */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('payments.status', 'failed')
                ->orWhere(fn ($q2) => $q2->where('payments.status', 'pending')->where('payments.attempts', '>=', 3));
        });
    }
}
