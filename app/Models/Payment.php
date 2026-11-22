<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processing_fee' => 'integer',
            'commission' => 'integer',
            'paid_at' => 'datetime',
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
     * The payout ledger is this table — gross, commission, the processor's fee
     * and the net all live on one row, so the ledger can never disagree with the
     * payment it describes.
     *
     * The fee is deducted here because the shop bears it: the processor takes
     * its cut out of the money as it arrives, so the shop was never owed that
     * part of the gross. Leaving it out made small jobs look profitable — the
     * ₱24 job on the books earns ₱0.36 of commission against ₱15.60 of fee.
     */
    public function net(): int
    {
        return self::netFrom($this->amount, $this->commission, $this->processing_fee);
    }

    /**
     * The same subtraction, for callers that hold the three integers rather than
     * a model.
     *
     * The payout CSV streams flat rows through a cursor on purpose — it never
     * builds a Payment — so it cannot call `net()` without giving that up. This
     * exists so it can still reach the one definition instead of writing
     * `amount - commission - fee` a second time and drifting from it later.
     */
    public static function netFrom(int $amount, int $commission, int $fee): int
    {
        return $amount - $commission - $fee;
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

    /** Payments that exhausted their webhook retries and need a human. */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('payments.status', 'failed')
                ->orWhere(fn ($q2) => $q2->where('payments.status', 'pending')->where('payments.attempts', '>=', 3));
        });
    }
}
