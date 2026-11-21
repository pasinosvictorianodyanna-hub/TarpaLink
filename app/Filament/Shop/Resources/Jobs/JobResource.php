<?php

namespace App\Filament\Shop\Resources\Jobs;

use App\Filament\Shop\Resources\Jobs\Pages\ListJobs;
use App\Filament\Shop\Resources\Jobs\Pages\ViewJob;
use App\Filament\Shop\Resources\Jobs\Schemas\JobInfolist;
use App\Filament\Shop\Resources\Jobs\Tables\JobsTable;
use App\Models\Job;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Jobs are created by the storefront, never by hand, so this resource is
 * read-and-advance only: no create page, no edit page, price is never editable.
 */
class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Jobs';

    protected static string|UnitEnum|null $navigationGroup = 'Work';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('shop_id', auth()->user()?->currentShopId() ?? 0);
    }

    /** Jobs are born in the storefront. Nothing in the panel may create, edit or delete one. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // No editable form: a job is a snapshot of what the customer bought.
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobs::route('/'),
            'view' => ViewJob::route('/{record}'),
        ];
    }
}
