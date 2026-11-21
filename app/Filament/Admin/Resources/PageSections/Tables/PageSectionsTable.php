<?php

namespace App\Filament\Admin\Resources\PageSections\Tables;

use App\Models\PageSection;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('sort')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('label')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('key')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('content.headline')
                    ->label('Headline / first line')
                    ->state(fn (PageSection $record): string => (string) (
                        $record->content['headline']
                        ?? $record->content['text']
                        ?? $record->content['title']
                        ?? $record->content['blurb']
                        ?? '—'
                    ))
                    ->limit(64)
                    ->wrap(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('updated_at')
                    ->label('Edited')
                    ->since()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No sections')
            ->emptyStateDescription('Run the seeder to create the six default sections.');
    }
}
