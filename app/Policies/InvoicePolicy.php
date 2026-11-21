<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/** Subscription invoices: the shop that owes them, and the platform that issued them. */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isShopOwner();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $user->belongsToShop($invoice->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }
}
