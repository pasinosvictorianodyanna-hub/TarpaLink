<?php

namespace App\Policies;

use App\Models\Proof;
use App\Models\User;

/**
 * Proofs are the shop's draft and the customer's decision: the shop uploads a
 * version, the customer approves or rejects it. Both sides can read it, and
 * nobody else can.
 */
class ProofPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->worksInShop();
    }

    public function view(User $user, Proof $proof): bool
    {
        $job = $proof->job;

        if (! $job) {
            return $user->isAdmin();
        }

        return $user->isAdmin()
            || $job->user_id === $user->id
            || $user->belongsToShop($job->shop_id);
    }

    /** Staff upload the artwork too — that is the job they are hired to do. */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->worksInShop();
    }

    public function update(User $user, Proof $proof): bool
    {
        $job = $proof->job;

        return $user->isAdmin()
            || ($job && $job->user_id === $user->id)
            || $user->belongsToShop($job?->shop_id);
    }

    public function delete(User $user, Proof $proof): bool
    {
        return $user->isAdmin();
    }
}
