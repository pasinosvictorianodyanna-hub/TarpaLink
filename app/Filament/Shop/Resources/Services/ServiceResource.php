<?php

namespace App\Filament\Shop\Resources\Services;

use App\Filament\Shop\Resources\Services\Pages\CreateService;
use App\Filament\Shop\Resources\Services\Pages\EditService;
use App\Filament\Shop\Resources\Services\Pages\ListServices;
use App\Filament\Shop\Resources\Services\Pages\ViewService;
use App\Filament\Shop\Resources\Services\RelationManagers\OptionsRelationManager;
use App\Filament\Shop\Resources\Services\RelationManagers\TiersRelationManager;
use App\Filament\Shop\Resources\Services\Schemas\ServiceForm;
use App\Filament\Shop\Resources\Services\Schemas\ServiceInfolist;
use App\Filament\Shop\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The shop's own catalogue. Scoped to the logged-in owner's shop at the query
 * level, so a shop can never see — or route to — another shop's services.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Services';

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('shop_id', auth()->user()?->currentShopId() ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TiersRelationManager::class,
            OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'view' => ViewService::route('/{record}'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
