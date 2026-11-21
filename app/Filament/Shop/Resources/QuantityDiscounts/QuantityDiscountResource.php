<?php

namespace App\Filament\Shop\Resources\QuantityDiscounts;

use App\Filament\Shop\Resources\QuantityDiscounts\Pages\CreateQuantityDiscount;
use App\Filament\Shop\Resources\QuantityDiscounts\Pages\EditQuantityDiscount;
use App\Filament\Shop\Resources\QuantityDiscounts\Pages\ListQuantityDiscounts;
use App\Filament\Shop\Resources\QuantityDiscounts\Schemas\QuantityDiscountForm;
use App\Filament\Shop\Resources\QuantityDiscounts\Tables\QuantityDiscountsTable;
use App\Models\QuantityDiscount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class QuantityDiscountResource extends Resource
{
    protected static ?string $model = QuantityDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Quantity discounts';

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'discount band';

    /** Shop-level bands, so they are scoped to the owner's shop like everything else. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('shop_id', auth()->user()?->currentShopId() ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return QuantityDiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuantityDiscountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuantityDiscounts::route('/'),
            'create' => CreateQuantityDiscount::route('/create'),
            'edit' => EditQuantityDiscount::route('/{record}/edit'),
        ];
    }
}
