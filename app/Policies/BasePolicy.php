<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared rules for the four roles: Guest, Customer, Shop Owner, Administrator.
 *
 * A shop owner is scoped to the single shop resolved from the shop_users pivot.
 * Nothing here trusts an id from the request — the caller must belong to the
 * shop that actually owns the record.
 */
abstract class BasePolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    protected function belongsToShop(User $user, ?int $shopId): bool
    {
        if ($shopId === null) {
            return false;
        }

        return $user->activeShop()?->id === $shopId;
    }

    /** Admin, or a member of the shop that owns the record. */
    protected function managesShop(User $user, ?int $shopId): bool
    {
        return $this->isAdmin($user) || $this->belongsToShop($user, $shopId);
    }
}
