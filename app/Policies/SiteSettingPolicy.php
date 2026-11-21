<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SiteSettingPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, SiteSetting $setting): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, SiteSetting $setting): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, SiteSetting $setting): bool
    {
        return $this->isAdmin($user);
    }
}
