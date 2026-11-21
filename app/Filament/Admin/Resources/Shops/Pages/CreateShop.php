<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Resources\Shops\ShopResource;
use App\Models\Plan;
use Filament\Resources\Pages\CreateRecord;

class CreateShop extends CreateRecord
{
    protected static string $resource = ShopResource::class;

    /** The plan owns the rate; a shop never carries a rate the plan does not define. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['plan'])) {
            $data['commission_rate'] = Plan::where('key', $data['plan'])->value('commission_rate')
                ?? ($data['commission_rate'] ?? 500);
        }

        return $data;
    }
}
