<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Resources\Shops\ShopResource;
use App\Models\Plan;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    /**
     * Keep shops.commission_rate in step with the chosen plan.
     *
     * Payouts already written are untouched — they carry their own rate
     * snapshot, which is the whole point of storing it per row.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['plan'])) {
            $data['commission_rate'] = Plan::where('key', $data['plan'])->value('commission_rate')
                ?? $this->record->commission_rate;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
