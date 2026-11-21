<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Shop $shop): bool
    {
        return $this->managesShop($user, $shop->id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /** A shop owner may edit their own storefront details, never another shop's. */
    public function update(User $user, Shop $shop): bool
    {
        return $this->managesShop($user, $shop->id);
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $this->isAdmin($user);
    }

    /** Approving or suspending a shop is an administrator action. */
    public function approve(User $user, Shop $shop): bool
    {
        return $this->isAdmin($user);
    }
}
