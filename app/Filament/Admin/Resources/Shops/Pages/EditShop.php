<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Actions\ShopActions;
use App\Filament\Admin\Resources\Shops\ShopResource;
use App\Models\Plan;
use App\Services\Billing;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ShopActions::approve(),
            ShopActions::reject(),
            ShopActions::suspend(),
            ShopActions::reopen(),
            ViewAction::make(),
        ];
    }

    /**
     * A plan is a bill, not a column.
     *
     * The form used to write `shops.plan` and `shops.commission_rate` directly,
     * which moved a shop to Growth (3%) while creating no `subscriptions` row
     * and no invoice. The discounted rate applied from the next job onward and
     * the monthly fee never did — the shop was granted a permanent discount by
     * an edit that looked like a label change.
     *
     * `Billing::subscribe()` is the only writer that keeps the three facts in
     * step: the plan, the rate, and the bill. It also voids the current month's
     * open invoice, because switching mid-month supersedes it rather than
     * stacking a second one.
     */
    protected function afterSave(): void
    {
        $shop = $this->record;

        // An edit that touches only the address or the coordinates must not
        // re-bill anything — this is the plan changing, not the form saving.
        if (! $shop->wasChanged('plan')) {
            return;
        }

        $plan = Plan::where('code', $shop->plan)->first();

        if ($plan === null) {
            return;
        }

        app(Billing::class)->subscribe($shop, $plan);
    }
}
