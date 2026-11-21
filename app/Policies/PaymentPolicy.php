<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->managesShop($user, $payment->job?->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /** Money rows are never edited by hand; the webhook owns them. */
    public function update(User $user, Payment $payment): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->isAdmin($user);
    }
}
