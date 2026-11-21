<?php

namespace App\Policies;

use App\Models\PriceOption;
use App\Models\User;

/** Add-ons (eyelets, lamination, extra prints) — same rule as the tiers they sit beside. */
class PriceOptionPolicy
{
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
