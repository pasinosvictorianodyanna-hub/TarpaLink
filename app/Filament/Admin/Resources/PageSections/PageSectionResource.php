<?php

namespace App\Filament\Admin\Resources\PageSections;

use App\Filament\Admin\Resources\PageSections\Pages\EditPageSection;
use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Admin\Resources\PageSections\Schemas\PageSectionForm;
use App\Filament\Admin\Resources\PageSections\Tables\PageSectionsTable;
use App\Models\PageSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The CMS. Copy and order only — every section's layout is fixed in Blade, so
 * creating a new key here would produce a row nothing renders. Sections can be
 * hidden or deleted, and the page degrades rather than breaking.
 */
class PageSectionResource extends Resource
{
    protected static ?string $model = PageSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Site content';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $slug = 'site-content';

    protected static ?string $modelLabel = 'section';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PageSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageSectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSections::route('/'),
            'edit' => EditPageSection::route('/{record}/edit'),
        ];
    }
}
