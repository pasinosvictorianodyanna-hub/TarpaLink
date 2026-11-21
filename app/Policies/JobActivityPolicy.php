<?php

namespace App\Policies;

use App\Models\JobActivity;
use App\Models\User;

/**
 * The status trail is append-only history. Everyone who can see the job can read
 * it; nobody edits it, because an audit log you can rewrite is not an audit log.
 */
class JobActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, JobActivity $activity): bool
    {
        $job = $activity->job;

        if (! $job) {
            return $user->isAdmin();
        }

        return $user->isAdmin()
            || $job->user_id === $user->id
            || $user->belongsToShop($job->shop_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, JobActivity $activity): bool
    {
        return false;
    }

    public function delete(User $user, JobActivity $activity): bool
    {
        return false;
    }
}
