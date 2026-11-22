<?php

namespace App\Filament\Admin\Resources\ShopRequests\Pages;

use App\Filament\Admin\Actions\ShopRequestActions;
use App\Filament\Admin\Resources\ShopRequests\ShopRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewShopRequest extends ViewRecord
{
    protected static string $resource = ShopRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ShopRequestActions::approve(),
            ShopRequestActions::reject(),
            ShopRequestActions::resend(),
        ];
    }
}
