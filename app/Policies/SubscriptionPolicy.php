<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->managesShop($user, $subscription->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $this->isAdmin($user);
    }
}
