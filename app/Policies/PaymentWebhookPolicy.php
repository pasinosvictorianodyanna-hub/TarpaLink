<?php

namespace App\Policies;

use App\Models\PaymentWebhook;
use App\Models\User;

class PaymentWebhookPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, PaymentWebhook $webhook): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PaymentWebhook $webhook): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, PaymentWebhook $webhook): bool
    {
        return $this->isAdmin($user);
    }
}
