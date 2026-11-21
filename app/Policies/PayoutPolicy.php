<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, Payout $payout): bool
    {
        return $this->managesShop($user, $payout->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /** Ledger rows are written by settlement, never edited. */
    public function update(User $user, Payout $payout): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Payout $payout): bool
    {
        return $this->isAdmin($user);
    }
}
