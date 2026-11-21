<?php

namespace App\Policies;

use App\Models\QuantityDiscount;
use App\Models\User;

class QuantityDiscountPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, QuantityDiscount $discount): bool
    {
        return $this->managesShop($user, $discount->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function update(User $user, QuantityDiscount $discount): bool
    {
        return $this->managesShop($user, $discount->shop_id);
    }

    public function delete(User $user, QuantityDiscount $discount): bool
    {
        return $this->managesShop($user, $discount->shop_id);
    }
}
