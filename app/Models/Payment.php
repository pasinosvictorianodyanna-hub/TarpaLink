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

    public function scopeSettled($query)
    {
        return $query->where('status', 'paid');
    }

    /** Payments that exhausted their webhook retries and need a human. */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'failed')
                ->orWhere(fn ($q2) => $q2->where('status', 'pending')->where('attempts', '>=', 3));
        });
    }
}
