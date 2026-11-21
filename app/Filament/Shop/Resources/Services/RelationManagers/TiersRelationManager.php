<?php

namespace App\Filament\Shop\Resources\Services\RelationManagers;

use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The size tiers of one service — the rows the price engine matches a size against.
 *
 * Tier is chosen by the LONG EDGE, so `max_width` really means "max long edge".
 * Editing any row changes the matrix fingerprint, which means the next quote gets
 * a new `matrix_version` while jobs already created keep theirs.
 */
class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Size tiers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Tier label')
                    ->placeholder('Large (≤96in)')
                    ->helperText('What the customer sees on the receipt.')
                    ->required()
                    ->maxLength(255),

                TextInput::make('min_width')
                    ->label('From (inches)')
                    ->numeric()
                    ->step('0.01')
                    ->default(0)
                    ->required(),

                TextInput::make('max_width')
                    ->label('Up to (inches)')
                    ->numeric()
                    ->step('0.01')
                    ->helperText('Longest side this tier covers. Leave empty for no upper limit.'),

                TextInput::make('unit_price')
                    ->label('Rate')
                    ->prefix('₱')
                    ->numeric()
                    ->step('0.01')
                    ->required()
                    ->helperText('Per square foot or per piece — whichever the service unit says.')
                    ->formatStateUsing(fn ($state) => Money::toPesos($state))
                    ->dehydrateStateUsing(fn ($state) => Money::toCentavos($state)),

                TextInput::make('sort')
                    ->label('Order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('label')
                    ->weight('medium'),

                TextColumn::make('min_width')
                    ->label('Range')
                    ->formatStateUsing(fn ($record): string => $record->max_width === null
                        ? "{$record->min_width} in and up"
                        : "{$record->min_width}–{$record->max_width} in"),

                TextColumn::make('unit_price')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),

                TextColumn::make('sort')
                    ->label('#')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add tier'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
