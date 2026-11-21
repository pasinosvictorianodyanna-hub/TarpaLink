<?php

namespace App\Filament\Shop\Resources\QuantityDiscounts\Pages;

use App\Filament\Shop\Resources\QuantityDiscounts\QuantityDiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuantityDiscount extends CreateRecord
{
    protected static string $resource = QuantityDiscountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shop_id'] = auth()->user()->currentShopId();

        return $data;
    }
}
