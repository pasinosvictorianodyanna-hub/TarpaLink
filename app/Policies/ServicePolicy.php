<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * The price matrix belongs to the shop that publishes it.
 *
 * The shop panel already scopes its queries to the logged-in shop, but that is a
 * convenience, not a boundary: a scoped query returns 404 for another shop's row,
 * while this policy is what actually says "no". Both are needed — the scope stops
 * accidental leaks, the policy stops deliberate ones.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, Service $service): bool
    {
        return $user->isAdmin() || $user->belongsToShop($service->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin() || $user->belongsToShop($service->shop_id);
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin() || $user->belongsToShop($service->shop_id);
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }
}
