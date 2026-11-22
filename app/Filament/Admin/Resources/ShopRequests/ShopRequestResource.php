<?php

namespace App\Filament\Admin\Resources\ShopRequests;

use App\Filament\Admin\Resources\ShopRequests\Pages\ListShopRequests;
use App\Filament\Admin\Resources\ShopRequests\Pages\ViewShopRequest;
use App\Filament\Admin\Resources\ShopRequests\Schemas\ShopRequestInfolist;
use App\Filament\Admin\Resources\ShopRequests\Tables\ShopRequestsTable;
use App\Models\ShopRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Shops asking to be let in.
 *
 * Separate from the Shops resource on purpose. A request is not a shop — there
 * is no owner account, no price matrix and no storefront — so putting requests
 * in the shop list would fill it with rows that cannot be managed as shops and
 * would inflate every count that says "shops".
 *
 * Approving here creates both the account and the shop (see `ShopOnboarding`);
 * this screen never edits them afterwards, which is what the Shops resource is
 * for.
 */
class ShopRequestResource extends Resource
{
    protected static ?string $model = ShopRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Shop requests';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    /** Above Shops: an unanswered application is the thing with a deadline. */
    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'shop request';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['shop', 'handler']);
    }

    /** The number waiting, so the queue is visible without opening it. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = ShopRequest::waiting()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return ShopRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShopRequestInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShopRequests::route('/'),
            'view' => ViewShopRequest::route('/{record}'),
        ];
    }
}
