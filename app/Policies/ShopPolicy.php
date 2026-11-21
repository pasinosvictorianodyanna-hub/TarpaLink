<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

/**
 * Who may see and change a shop.
 *
 * Admission (`approve`, `reject`, `suspend`) is administrator-only and is the
 * reason this policy exists separately from the shop-scoped ones: a shop owner
 * may edit their own shop's details but must never be able to approve itself.
 */
class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, Shop $shop): bool
    {
        return $user->isAdmin() || $user->belongsToShop($shop);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->isAdmin() || $user->isOwnerOf($shop);
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }

    public function suspend(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }
}
