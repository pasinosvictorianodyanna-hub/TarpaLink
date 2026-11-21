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
     * The payout ledger is this table — gross, commission and net all live on
     * one row, so the ledger can never disagree with the payment it describes.
     */
    public function net(): int
    {
        return $this->amount - $this->commission;
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
