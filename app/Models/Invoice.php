<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly platform-fee invoice. Never touched by a plan change — the fee it
 * was issued for is the fee the shop agreed to at the time.
 */
class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'paid' => 'Paid',
            'void' => 'Void',
            default => ucfirst($this->status),
        };
    }
}
