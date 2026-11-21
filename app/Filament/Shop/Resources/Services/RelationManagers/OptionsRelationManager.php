<?php

namespace App\Filament\Shop\Resources\Services\RelationManagers;

use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Finishing add-ons priced separately: eyelets, hemming, lamination. */
class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Finishing options';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->placeholder('Eyelets')
                    ->required()
                    ->maxLength(255),

                Select::make('unit')
                    ->options([
                        'pc' => 'Per piece',
                        'ft' => 'Per foot',
                        'sqft' => 'Per square foot',
                    ])
                    ->default('pc')
                    ->required()
                    ->native(false),

                TextInput::make('price')
                    ->label('Price')
                    ->prefix('₱')
                    ->numeric()
                    ->step('0.01')
                    ->required()
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
            ->recordTitleAttribute('name')
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium'),

                TextColumn::make('unit')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pc' => 'per piece',
                        'ft' => 'per foot',
                        'sqft' => 'per sq ft',
                        default => $state,
                    }),

                TextColumn::make('price')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
            ])
            ->headerActions([
                CreateAction::make()->label('Add option'),
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
