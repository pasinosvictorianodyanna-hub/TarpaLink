<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy extends BasePolicy
{
    /** Everyone signed in may read the plan catalogue — the billing screen needs it. */
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
        return $this->isAdmin($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }
}
