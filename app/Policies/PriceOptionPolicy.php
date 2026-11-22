<?php

namespace App\Policies;

use App\Models\PriceOption;
use App\Models\User;

/** Add-ons (eyelets, lamination, extra prints) — same rule as the tiers they sit beside. */
class PriceOptionPolicy
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

    public function view(User $user, PriceOption $option): bool
    {
        return $user->isAdmin() || $user->belongsToShop($option->service?->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function update(User $user, PriceOption $option): bool
    {
        return $user->isAdmin() || $user->belongsToShop($option->service?->shop_id);
    }

    public function delete(User $user, PriceOption $option): bool
    {
        return $user->isAdmin() || $user->belongsToShop($option->service?->shop_id);
    }
}
