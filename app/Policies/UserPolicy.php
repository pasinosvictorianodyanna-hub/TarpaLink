<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->isAdmin($user) || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isAdmin($user);
    }

    /** Nobody deletes themselves out of the admin seat. */
    public function delete(User $user, User $target): bool
    {
        return $this->isAdmin($user) && $user->id !== $target->id;
    }
}
