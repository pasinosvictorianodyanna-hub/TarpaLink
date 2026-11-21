<?php

namespace App\Policies;

use App\Models\PriceOption;
use App\Models\User;

class PriceOptionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, PriceOption $option): bool
    {
        return $this->managesShop($user, $option->service?->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function update(User $user, PriceOption $option): bool
    {
        return $this->managesShop($user, $option->service?->shop_id);
    }

    public function delete(User $user, PriceOption $option): bool
    {
        return $this->managesShop($user, $option->service?->shop_id);
    }
}
