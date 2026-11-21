<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** The customer who placed it, the shop fulfilling it, or an administrator. */
    public function view(User $user, Job $job): bool
    {
        return $this->isAdmin($user)
            || $job->user_id === $user->id
            || $this->belongsToShop($user, $job->shop_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only the shop moves a job through production. */
    public function update(User $user, Job $job): bool
    {
        return $this->managesShop($user, $job->shop_id);
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->isAdmin($user);
    }
}
