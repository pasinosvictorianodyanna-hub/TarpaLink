<?php

namespace App\Filament\Shop\Concerns;

use App\Models\Shop;

/**
 * Every shop-panel screen works on the shop the signed-in user operates.
 *
 * There is no shop picker: an owner attached to two shops sees the first, which
 * is the same shop `User::currentShop()` hands the panel elsewhere. Keeping the
 * lookup in one trait means a widget can never accidentally query unscoped.
 */
trait ScopesToShop
{
    protected function shop(): ?Shop
    {
        return auth()->user()?->currentShop();
    }
}
