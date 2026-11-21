<?php

namespace App\Policies;

use App\Models\PriceTier;
use App\Models\User;

class PriceTierPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, PriceTier $tier): bool
    {
        return $this->managesShop($user, $tier->service?->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function update(User $user, PriceTier $tier): bool
    {
        return $this->managesShop($user, $tier->service?->shop_id);
    }

    public function delete(User $user, PriceTier $tier): bool
    {
        return $this->managesShop($user, $tier->service?->shop_id);
    }
}
