<?php

namespace App\Support;

use App\Models\Shop;

/**
 * The shop the current panel user operates.
 *
 * Resolved once per request from the shop_users pivot. Every shop-panel query is
 * scoped through this rather than a global scope on the model: a global scope
 * would silently return nothing for admins and queue workers, where there is no
 * "current shop" at all. See docs/ISSUES.md #1.
 */
class ShopContext
{
    private ?Shop $resolved = null;

    private bool $resolvedOnce = false;

    public function shop(): ?Shop
    {
        if ($this->resolvedOnce) {
            return $this->resolved;
        }

        $this->resolvedOnce = true;

        return $this->resolved = auth()->user()?->activeShop();
    }

    public function id(): ?int
    {
        return $this->shop()?->id;
    }

    /** True when the caller belongs to this shop, or is a platform administrator. */
    public function owns(int $shopId): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $this->id() === $shopId;
    }
}
