<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/** A subscription belongs to one shop; only `Billing` writes them. */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin() || $user->belongsToShop($subscription->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }
}
