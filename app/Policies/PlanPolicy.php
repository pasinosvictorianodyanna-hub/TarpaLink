<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Plans are readable by everyone with an account — a shop has to be able to see
 * what it could upgrade to — but only the platform can change what a plan costs.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plan $plan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->isAdmin();
    }
}
