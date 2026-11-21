<?php

namespace App\Filament\Admin\Resources\PageSections\Schemas;

use App\Models\PageSection;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Editing copy only. The layout of every section lives in Blade — this form can
 * change the words and the order, never the structure.
 *
 * Fields are chosen by the section's `key`, so the hero gets a headline field
 * and testimonials get a repeater of quotes rather than a raw JSON blob.
 */
class PageSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Section')
                    ->columns(4)
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('status')
                            ->options([
                                'published' => 'Published',
                                'draft' => 'Draft',
                                'hidden' => 'Hidden',
                            ])
                            ->default('published')
                            ->required()
                            ->native(false)
                            ->columnSpan(2)
                            ->helperText('Not Published removes it from the site.'),

                        TextInput::make('sort')
                            ->label('Order')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('Copy')
                    ->schema(fn (?PageSection $record): array => self::fieldsFor($record?->key)),
            ]);
    }

    /** @return array<int, Component> */
    private static function fieldsFor(?string $key): array
    {
        return match ($key) {
            'hero' => [
                TextInput::make('content.eyebrow')
                    ->label('Eyebrow')
                    ->maxLength(120),

                Textarea::make('content.headline')
                    ->label('Headline')
                    ->rows(2)
                    ->required()
                    ->helperText('Line breaks are preserved on the page.'),

                Textarea::make('content.subheadline')
                    ->label('Sub-headline')
                    ->rows(2),

                TextInput::make('content.primary_label')
                    ->label('Button label')
                    ->maxLength(60),

                TextInput::make('content.primary_link')
                    ->label('Button link')
                    ->maxLength(255),
            ],

            'banner' => [
                TextInput::make('content.text')
                    ->label('Banner text')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('content.link_label')
                    ->label('Link label')
                    ->maxLength(60),

                TextInput::make('content.link')
                    ->label('Link')
                    ->maxLength(255),
            ],

            'featured_shops' => [
                TextInput::make('content.title')
                    ->label('Heading')
                    ->required(),

                TextInput::make('content.subtitle')
                    ->label('Sub-heading')
                    ->columnSpanFull(),
            ],

            'testimonials' => [
                TextInput::make('content.title')
                    ->label('Heading')
                    ->columnSpanFull(),

                Repeater::make('content.items')
                    ->label('Quotes')
                    ->schema([
                        Textarea::make('quote')
                            ->rows(2)
                            ->required(),

                        TextInput::make('name')
                            ->required(),

                        TextInput::make('role')
                            ->placeholder('Events coordinator, QC'),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add a testimonial')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            ],

            'faq' => [
                TextInput::make('content.title')
                    ->label('Heading')
                    ->columnSpanFull(),

                Repeater::make('content.items')
                    ->label('Questions')
                    ->schema([
                        TextInput::make('q')
                            ->label('Question')
                            ->required(),

                        Textarea::make('a')
                            ->label('Answer')
                            ->rows(3)
                            ->required(),
                    ])
                    ->addActionLabel('Add a question')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['q'] ?? null),
            ],

            'footer' => [
                Textarea::make('content.blurb')
                    ->label('Footer blurb')
                    ->rows(3),
            ],

            // Unknown section: fall back to free-form key/value rather than
            // refusing to open the record.
            default => [
                KeyValue::make('content')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add a field'),
            ],
        };
    }
}
