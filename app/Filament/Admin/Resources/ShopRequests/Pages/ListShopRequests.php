<?php

namespace App\Filament\Admin\Resources\ShopRequests\Pages;

use App\Filament\Admin\Resources\ShopRequests\ShopRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListShopRequests extends ListRecords
{
    protected static string $resource = ShopRequestResource::class;

    protected function getHeaderActions(): array
    {
        // Requests come from the public form, never from the panel.
        return [];
    }
}
