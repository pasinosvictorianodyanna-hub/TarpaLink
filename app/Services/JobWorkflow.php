<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single definition of which status changes are legal.
 *
 * The Filament UI asks this class what to offer, and this class re-checks on
 * save. Hiding a button is not a rule — a replayed request or a tampered form
 * must still be rejected here.
 */
final class JobWorkflow
{
    /**
     * Forward path plus the one terminal branch.
     *
     * `cancelled` is reachable from any pre-production state only: once a job is
     * on the press the shop has already spent material, so cancelling it is a
     * refund conversation, not a status edit.
     */
    public const TRANSITIONS = [
        'quoted' => ['awaiting_proof', 'cancelled'],
        'awaiting_proof' => ['approved', 'cancelled'],
        'approved' => ['in_production', 'cancelled'],
        'in_production' => ['ready'],
        'ready' => ['collected'],
        'collected' => [],
        'cancelled' => [],
    ];

    /** Statuses that appear as columns on the production board, in order. */
    public const BOARD = ['quoted', 'awaiting_proof', 'approved', 'in_production', 'ready'];

    /** @return array<int, string> */
    public static function allowedFrom(?string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(?string $from, string $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }

    /**
     * @throws ValidationException when the move is not allowed
     */
    public static function transition(Job $job, string $to, ?User $by = null, ?string $note = null): void
    {
        if (! self::canTransition($job->status, $to)) {
            throw ValidationException::withMessages([
                'status' => "A job that is \"{$job->statusLabel()}\" cannot move to \"{$to}\".",
            ]);
        }

        $from = $job->status;

        // Status and audit row live or die together. A transition written
        // without its activity row would break the audit trail (FR-43) in a way
        // nobody notices until somebody asks who moved a job.
        DB::transaction(function () use ($job, $to, $from, $by, $note) {
            $job->status = $to;

            // Stamped here, in the only place a transition can happen, so a job
            // can never be `collected` without a timestamp for the on-time rate.
            if ($to === 'collected') {
                $job->collected_at = now();
            }

            $job->save();

            $job->activities()->create([
                'user_id' => $by?->id,
                'kind' => JobActivity::TRANSITION,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);
        });
    }
}
