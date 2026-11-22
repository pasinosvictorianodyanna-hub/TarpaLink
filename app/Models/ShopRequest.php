<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request from a shop to join the platform.
 *
 * It is not an account and not a shop. It holds the details an administrator
 * needs to decide, and — once approved — a link to the shop it became. Until
 * then it is a row in a queue, which is exactly what it should be.
 */
class ShopRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Still waiting on an administrator. */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Waiting',
        };
    }

    /** The services they listed, one per line, as they typed them. */
    public function serviceLines(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->services))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
