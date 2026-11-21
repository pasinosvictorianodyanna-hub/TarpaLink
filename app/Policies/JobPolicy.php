<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

/**
 * A job is visible to the customer who ordered it and to the shop that prints it.
 *
 * Nobody else — not another customer, not another shop — and no one but an
 * administrator may delete one. Status changes go through `JobWorkflow`, but
 * `update` still has to say who is allowed to attempt them at all.
 */
class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, Job $job): bool
    {
        return $user->isAdmin()
            || $job->user_id === $user->id
            || $user->belongsToShop($job->shop_id);
    }

    /** Customers create jobs; the panel never does. */
    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isAdmin();
    }

    public function update(User $user, Job $job): bool
    {
        return $user->isAdmin() || $user->belongsToShop($job->shop_id);
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->isAdmin();
    }
}
