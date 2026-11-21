<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

/** Platform-wide settings: administrator only, like the CMS. */
class SiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SiteSetting $setting): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SiteSetting $setting): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SiteSetting $setting): bool
    {
        return $user->isAdmin();
    }
}
