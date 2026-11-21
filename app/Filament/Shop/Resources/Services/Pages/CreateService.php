<?php

namespace App\Filament\Shop\Resources\Services\Pages;

use App\Filament\Shop\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    /** The shop is never chosen in the form — it comes from the logged-in owner. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shop_id'] = auth()->user()->currentShopId();

        return $data;
    }
}
