<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a job's history. Append-only — nothing edits or deletes these.
 *
 * A row is either a `transition` (the job moved, so both statuses are set) or a
 * `note` (something was said about the job and nothing moved). The distinction
 * is explicit because the two are counted differently: only transitions belong
 * in a cycle-time report.
 */
class JobActivity extends Model
{
    public const TRANSITION = 'transition';

    public const NOTE = 'note';

    protected $guarded = [];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTransition(): bool
    {
        return $this->kind === self::TRANSITION;
    }
}
