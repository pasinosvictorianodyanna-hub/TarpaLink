<?php

namespace App\Filament\Shop\Resources\QuantityDiscounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuantityDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('min_qty')
            ->columns([
                TextColumn::make('min_qty')
                    ->label('From quantity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('percent')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state): string => "{$state}%")
                    ->weight('medium'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No discount bands yet')
            ->emptyStateDescription('Bands are applied to the whole job once the quantity is reached. Most shops start at 3+ = 5%.');
    }
}
