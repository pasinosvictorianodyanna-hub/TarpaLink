<?php

namespace App\Policies;

use App\Models\PriceTier;
use App\Models\User;

/**
 * A tier has no `shop_id` of its own — it inherits one through its service.
 * Guarding it here means the relation manager inside the service screen cannot
 * be used as a side door into another shop's price matrix.
 */
class PriceTierPolicy
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

    public function view(User $user, PriceTier $tier): bool
    {
        return $user->isAdmin() || $user->belongsToShop($tier->service?->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function update(User $user, PriceTier $tier): bool
    {
        return $user->isAdmin() || $user->belongsToShop($tier->service?->shop_id);
    }

    public function delete(User $user, PriceTier $tier): bool
    {
        return $user->isAdmin() || $user->belongsToShop($tier->service?->shop_id);
    }
}
