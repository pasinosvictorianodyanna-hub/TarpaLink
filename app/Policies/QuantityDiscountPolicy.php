<?php

namespace App\Policies;

use App\Models\QuantityDiscount;
use App\Models\User;

/** Volume discount bands belong to one shop's matrix. */
class QuantityDiscountPolicy
{

    /**
     * Staff work jobs, not prices or billing. One gate for every ability so a
     * forgotten button cannot become a write path.
     */
    public function before(User $user, string $ability): ?bool
    {
        $money = ['viewAny', 'create', 'update', 'delete', 'restore', 'forceDelete'];

        if (in_array($ability, $money, true) && ! $user->canManagePrices()) {
            return false;
        }

        return null;
    }

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
