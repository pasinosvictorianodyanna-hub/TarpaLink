<?php

namespace App\Filament\Admin\Resources\SiteSettings;

use App\Filament\Admin\Resources\SiteSettings\Pages\CreateSiteSetting;
use App\Filament\Admin\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Admin\Resources\SiteSettings\Pages\ListSiteSettings;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(80)
                ->unique(ignoreRecord: true)
                ->helperText('Read by the app. Changing a key breaks whatever looks it up.'),
            Textarea::make('value')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->fontFamily('mono')->searchable()->weight('semibold'),
                TextColumn::make('value')->limit(60)->wrap()->searchable(),
                TextColumn::make('updated_at')->since()->label('Edited'),
            ])
            ->defaultSort('key')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteSettings::route('/'),
            'create' => CreateSiteSetting::route('/create'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
