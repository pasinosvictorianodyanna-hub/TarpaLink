<?php

namespace App\Filament\Admin\Resources\Shops;

use App\Filament\Admin\Resources\Shops\Pages\CreateShop;
use App\Filament\Admin\Resources\Shops\Pages\EditShop;
use App\Filament\Admin\Resources\Shops\Pages\ListShops;
use App\Filament\Admin\Resources\Shops\Schemas\ShopForm;
use App\Filament\Admin\Resources\Shops\Tables\ShopsTable;
use App\Models\Shop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Shops';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ShopForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShopsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShops::route('/'),
            'create' => CreateShop::route('/create'),
            'edit' => EditShop::route('/{record}/edit'),
        ];
    }
}
