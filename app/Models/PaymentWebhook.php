<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The audit trail for provider callbacks. Doubles as the replay guard: a repeated
 * event_id is rejected before any money moves.
 */
class PaymentWebhook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function failedPermanently(): bool
    {
        return $this->status === 'failed' && $this->attempts >= 3;
    }
}
