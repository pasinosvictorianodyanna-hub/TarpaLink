<?php

namespace App\Policies;

use App\Models\QuantityDiscount;
use App\Models\User;

/** Volume discount bands belong to one shop's matrix. */
class QuantityDiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, QuantityDiscount $discount): bool
    {
        return $user->isAdmin() || $user->belongsToShop($discount->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function update(User $user, QuantityDiscount $discount): bool
    {
        return $user->isAdmin() || $user->belongsToShop($discount->shop_id);
    }

    public function delete(User $user, QuantityDiscount $discount): bool
    {
        return $user->isAdmin() || $user->belongsToShop($discount->shop_id);
    }
}
