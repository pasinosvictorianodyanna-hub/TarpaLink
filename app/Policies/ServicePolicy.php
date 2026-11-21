<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, Service $service): bool
    {
        return $this->managesShop($user, $service->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function update(User $user, Service $service): bool
    {
        return $this->managesShop($user, $service->shop_id);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->managesShop($user, $service->shop_id);
    }
}
