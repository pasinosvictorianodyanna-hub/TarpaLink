<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;

/**
 * Shop admission.
 *
 * Approval is the one gate that decides whether a shop can trade at all: the
 * public storefront only lists `active` shops, and `User::canAccessPanel()`
 * refuses the shop panel for anything else. Both the approval queue widget and
 * the shops resource call this class, so there is exactly one place where the
 * status changes and exactly one place where the reason is written.
 */
class ShopApproval
{
    public function approve(Shop $shop, ?User $by = null): void
    {
        $shop->forceFill([
            'status' => 'active',
            'approved_at' => $shop->approved_at ?? now(),
            'approved_by' => $shop->approved_by ?? $by?->id,
            'status_changed_by' => $by?->id,
            'status_reason' => null,
        ])->save();
    }

    /**
     * A rejection is a suspension with a reason. Keeping one column for both
     * means a shop that was approved and later suspended lands in exactly the
     * same state as one that was never approved — which is what the lockout
     * checks, and what the shop is told.
     */
    public function reject(Shop $shop, string $reason, ?User $by = null): void
    {
        $this->close($shop, $reason, $by);
    }

    public function suspend(Shop $shop, string $reason, ?User $by = null): void
    {
        $this->close($shop, $reason, $by);
    }

    /** Put a shop back in the queue instead of approving it outright. */
    public function reopen(Shop $shop, ?User $by = null): void
    {
        $shop->forceFill([
            'status' => 'pending',
            'status_reason' => null,
            'status_changed_by' => $by?->id,
        ])->save();
    }

    private function close(Shop $shop, string $reason, ?User $by): void
    {
        $shop->forceFill([
            'status' => 'suspended',
            'status_reason' => $reason,
            'status_changed_by' => $by?->id,
        ])->save();
    }
}
