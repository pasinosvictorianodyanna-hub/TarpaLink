<?php

namespace App\Policies;

use App\Models\JobLine;
use App\Models\User;

/**
 * Priced lines are a snapshot of a job, so they inherit the job's visibility.
 * They are never written by hand — the calculator creates them at checkout.
 */
class JobLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, JobLine $line): bool
    {
        $job = $line->job;

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

    public function update(User $user, JobLine $line): bool
    {
        return false;
    }

    public function delete(User $user, JobLine $line): bool
    {
        return $user->isAdmin();
    }
}
