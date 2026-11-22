<?php

namespace App\Policies;

use App\Models\User;

/**
 * Accounts are administrator territory: creating staff, changing roles and
 * suspending someone are the only ways to escalate privilege in this system, so
 * no shop owner may touch a user record — not even their own role.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->id === $target->id) {
            return false;
        }

        // Deleting a person cascades to their jobs, and a job with a payment
        // against it cannot be deleted — that payment is the record of money
        // moving, and the ledger is computed from it. An account with only
        // abandoned, unpaid jobs still deletes cleanly, which is the case this
        // action exists for.
        return ! $target->jobs()->whereHas('payment')->exists();
    }
}
