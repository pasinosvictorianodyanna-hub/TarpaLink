<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payment records are financial history: readable by the shop that earned the
 * money and by the customer who paid it, writable by nobody in the panel.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->worksInShop();
    }

    public function view(User $user, Payment $payment): bool
    {
        $job = $payment->job;

        if (! $job) {
            return $user->isAdmin();
        }

        return $user->isAdmin()
            || $job->user_id === $user->id
            || $user->belongsToShop($job->shop_id);
    }

    /** Only the PayMongo webhook creates payments. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Release a shop's money.
     *
     * `update` is false for everyone because the amount, commission and status
     * are the webhook's to write. Settling is the one field an administrator
     * owns — it records that the platform paid the shop, which is a fact about
     * the platform, not about the payment.
     */
    public function settle(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
