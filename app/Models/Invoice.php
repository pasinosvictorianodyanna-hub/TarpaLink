<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bill for one billing period.
 *
 * Three statuses, and only one of them is a person's decision: `open` means
 * issued and not yet settled, `paid` means somebody recorded the money, `void`
 * means it was withdrawn and will never be collected. **Overdue is not one of
 * them** — it is `open` plus a `due_at` in the past, computed on read. A stored
 * `overdue` would need a nightly job to keep it true, and would be wrong every
 * night between the deadline and the run.
 */
class Invoice extends Model
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** Issued, unpaid, and past the date it was due. */
    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** How late, in whole days. Zero when it is not late. */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_at->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    /** The month this invoice covers, for a screen with no room for two dates. */
    public function periodLabel(): string
    {
        if ($this->period_start === null) {
            return $this->issued_at?->format('M Y') ?? '—';
        }

        return $this->period_start->format('M Y');
    }

    /** @param  Builder<Invoice>  $query */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('invoices.status', self::OPEN);
    }
}
