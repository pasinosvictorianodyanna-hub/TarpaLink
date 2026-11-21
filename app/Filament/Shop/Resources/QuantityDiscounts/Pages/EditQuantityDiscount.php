<?php

namespace App\Filament\Shop\Resources\QuantityDiscounts\Pages;

use App\Filament\Shop\Resources\QuantityDiscounts\QuantityDiscountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuantityDiscount extends EditRecord
{
    protected static string $resource = QuantityDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
