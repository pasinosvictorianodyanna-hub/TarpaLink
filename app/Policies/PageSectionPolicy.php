<?php

namespace App\Policies;

use App\Models\PageSection;
use App\Models\User;

/**
 * Site copy is platform-owned. A shop owner editing the homepage headline is the
 * exact mistake this policy exists to prevent. See docs/ISSUES.md #10.
 */
class PageSectionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, PageSection $section): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, PageSection $section): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, PageSection $section): bool
    {
        return $this->isAdmin($user);
    }
}
