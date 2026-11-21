<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per captured payment: what the shop earned and what the platform took.
 *
 * Both amounts are snapshots. They are written once, at settlement, from the
 * shop's rate at that moment — so a later plan change cannot rewrite a closed
 * month. See docs/ISSUES.md #7 and #11.
 */
class Payout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross' => 'integer',
            'commission' => 'integer',
            'net' => 'integer',
            'commission_rate' => 'integer',
            'payout_date' => 'date',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'scheduled' => 'Scheduled',
            'paid' => 'Paid out',
            default => ucfirst($this->status),
        };
    }

    public function commissionLabel(): string
    {
        return rtrim(rtrim(number_format($this->commission_rate / 100, 2, '.', ''), '0'), '.').'%';
    }
}
