<?php

namespace App\Filament\Admin\Resources\Shops\Tables;

use App\Filament\Admin\Actions\ShopActions;
use App\Models\Shop;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShopsTable
{
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'pending' => 'warning',
            'suspended' => 'danger',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Shop $record): string => $record->slug),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => self::statusColor($state)),

                TextColumn::make('planDefinition.name')
                    ->label('Plan')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('commission_rate')
                    ->label('Take rate')
                    ->state(fn (Shop $record): string => $record->commissionLabel()),

                TextColumn::make('jobs_count')
                    ->counts('jobs')
                    ->label('Jobs')
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Applied')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending review',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                SelectFilter::make('plan')
                    ->relationship('planDefinition', 'name'),
            ])
            ->recordActions([
                ShopActions::approve(),
                ShopActions::reject(),
                ShopActions::suspend(),
                ShopActions::reopen(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
