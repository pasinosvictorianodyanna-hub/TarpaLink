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
 *
 * A note may have arrived by email. That is a `source`, not a third kind: it is
 * still something said about the job with nothing moving, and counting it
 * separately would mean every report has to know about mail.
 */
class JobActivity extends Model
{
    public const TRANSITION = 'transition';

    public const NOTE = 'note';

    public const EMAIL = 'email';

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

    public function isEmail(): bool
    {
        return $this->source === self::EMAIL;
    }

    /**
     * The one-line entry in the job's history.
     *
     * Lives here rather than in the infolist because it is the same sentence
     * wherever the trail is read — the panel today, an export or a receipt
     * tomorrow — and because a formatted string is easier to assert on than a
     * rendered Filament component.
     */
    public function headline(): string
    {
        $when = $this->created_at?->format('d M Y H:i') ?? '';
        $who = $this->user ? ' · '.$this->user->name : '';

        if ($this->isTransition()) {
            return sprintf(
                '%s · %s → %s%s',
                $when,
                $this->from_status ? (Job::STATUS_LABELS[$this->from_status] ?? $this->from_status) : 'created',
                Job::STATUS_LABELS[$this->to_status] ?? (string) $this->to_status,
                $who,
            );
        }

        return sprintf('%s · %s%s', $when, $this->note ?? 'Note', $who);
    }

    /**
     * The text of an emailed reply, one entry per line.
     *
     * Returned as separate entries rather than one string with newlines in it:
     * the history renders each entry on its own line, and a string would arrive
     * with its line breaks collapsed into spaces. Capped, because a quoted
     * thread can be a hundred lines long and the history is not the place to
     * read it.
     *
     * @return array<int, string>
     */
    public function bodyLines(int $limit = 12): array
    {
        if (blank($this->body)) {
            return [];
        }

        $lines = collect(preg_split('/\R/', (string) $this->body) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return [];
        }

        $shown = $lines->take($limit)->values()->all();
        $last = count($shown) - 1;

        // Quote marks on the first and last line only, so a reply reads as
        // something a person said rather than as another line of system log.
        $shown[0] = '“'.$shown[0];
        $shown[$last] .= $lines->count() > $limit ? ' …' : '”';

        return $shown;
    }
}
