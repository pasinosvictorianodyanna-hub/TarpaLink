<?php

namespace App\Filament\Shop\Resources\Services\Tables;

use App\Models\Service;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('unit')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'sqft' ? 'per sq ft' : 'per piece'),

                TextColumn::make('tiers_count')
                    ->counts('tiers')
                    ->label('Tiers'),

                TextColumn::make('options_count')
                    ->counts('options')
                    ->label('Options'),

                TextColumn::make('entry_price')
                    ->label('From')
                    ->state(fn (Service $record): string => Money::format($record->tiers->min('unit_price'))),

                IconColumn::make('active')
                    ->boolean(),

                TextColumn::make('sort')
                    ->label('#')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
