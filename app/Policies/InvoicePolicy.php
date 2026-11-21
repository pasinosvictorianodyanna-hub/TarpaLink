<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->isShopOwner();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->managesShop($user, $invoice->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->isAdmin($user);
    }
}
