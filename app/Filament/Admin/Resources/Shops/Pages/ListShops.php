<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Resources\Shops\ShopResource;
use App\Models\Shop;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    /** The queue first: an admin who opens this page is usually here to approve. */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->badge(fn (): int => $this->pendingCount())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),

            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'suspended')),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    private function pendingCount(): int
    {
        return Shop::where('status', 'pending')->count();
    }
}
