<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Actions\ShopActions;
use App\Filament\Admin\Resources\Shops\ShopResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShop extends ViewRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ShopActions::approve(),
            ShopActions::reject(),
            ShopActions::suspend(),
            ShopActions::reopen(),
            EditAction::make(),
        ];
    }
}
