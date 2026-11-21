<?php

namespace App\Policies;

use App\Models\PageSection;
use App\Models\User;

/**
 * Site copy is the platform's voice, not a shop's.
 *
 * Read access is deliberately admin-only too: the storefront renders sections
 * through the model directly, so it does not need a Gate check, and no shop
 * panel has any business reading the CMS table.
 */
class PageSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PageSection $section): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PageSection $section): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PageSection $section): bool
    {
        return $user->isAdmin();
    }
}
