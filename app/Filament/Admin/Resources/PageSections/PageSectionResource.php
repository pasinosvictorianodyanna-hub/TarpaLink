<?php

namespace App\Filament\Admin\Resources\PageSections;

use App\Filament\Admin\Resources\PageSections\Pages\EditPageSection;
use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections;
use App\Models\PageSection;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The CMS. Copy and order only — layout stays in Blade and Vue. See #10.
 *
 * Fields are shown per section key rather than as one JSON blob: an editor should
 * see "Headline", not `{"headline": ...}`. The `content` JSON column is bound to
 * a Group with statePath, so the nested keys round-trip without custom mapping.
 */
class PageSectionResource extends Resource
{
    protected static ?string $model = PageSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Page sections';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canCreate(): bool
    {
        // The six sections are a fixed contract with the Blade views. Adding a
        // seventh here would render nothing.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Section')
                ->columns(3)
                ->schema([
                    TextInput::make('label')->required()->maxLength(80),
                    Select::make('status')
                        ->options([
                            'published' => 'Published',
                            'draft' => 'Draft',
                            'hidden' => 'Hidden',
                        ])
                        ->required()
                        ->default('published')
                        ->helperText('Hidden sections disappear from the site.'),
                    TextInput::make('sort')->numeric()->default(0)->helperText('Lower sorts first.'),
                ]),

            Section::make('Content')
                ->schema([
                    Group::make([
                        // Homepage hero
                        TextInput::make('eyebrow')->maxLength(120)->visible(fn (?PageSection $record) => $record?->key === 'hero'),
                        TextInput::make('headline')->maxLength(180)->visible(fn (?PageSection $record) => $record?->key === 'hero'),
                        Textarea::make('subheadline')->rows(2)->visible(fn (?PageSection $record) => $record?->key === 'hero'),
                        TextInput::make('primary_label')->maxLength(60)->visible(fn (?PageSection $record) => $record?->key === 'hero'),
                        TextInput::make('primary_link')->maxLength(120)->visible(fn (?PageSection $record) => $record?->key === 'hero'),

                        // Announcement banner
                        TextInput::make('text')->maxLength(180)->visible(fn (?PageSection $record) => $record?->key === 'banner'),
                        TextInput::make('link_label')->maxLength(60)->visible(fn (?PageSection $record) => $record?->key === 'banner'),
                        TextInput::make('link')->maxLength(120)->visible(fn (?PageSection $record) => $record?->key === 'banner'),

                        // Featured shops / testimonials / faq titles
                        TextInput::make('title')->maxLength(120)->visible(fn (?PageSection $record) => in_array($record?->key, ['featured_shops', 'testimonials', 'faq'], true)),
                        TextInput::make('subtitle')->maxLength(180)->visible(fn (?PageSection $record) => $record?->key === 'featured_shops'),

                        // Footer
                        Textarea::make('blurb')->rows(3)->visible(fn (?PageSection $record) => $record?->key === 'footer'),

                        // One repeater, two shapes. Two repeaters both named
                        // `items` would fight over the same JSON key on save.
                        Repeater::make('items')
                            ->visible(fn (?PageSection $record) => in_array($record?->key, ['testimonials', 'faq'], true))
                            ->schema(fn (?PageSection $record) => $record?->key === 'faq'
                                ? [
                                    TextInput::make('q')->label('Question')->required(),
                                    Textarea::make('a')->label('Answer')->rows(2)->required(),
                                ]
                                : [
                                    Textarea::make('quote')->rows(2)->required(),
                                    TextInput::make('name')->required(),
                                    TextInput::make('role'),
                                ])
                            ->columns(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['name'] ?? $state['q'] ?? 'Item'),
                    ])->statePath('content'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')->label('#')->sortable()->width('60px'),
                TextColumn::make('label')->searchable()->weight('semibold'),
                TextColumn::make('key')->fontFamily('mono')->color('gray')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('updated_at')->since()->label('Edited'),
            ])
            ->defaultSort('sort')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSections::route('/'),
            'edit' => EditPageSection::route('/{record}/edit'),
        ];
    }
}
