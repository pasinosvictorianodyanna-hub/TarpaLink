<?php

namespace App\Filament\Admin\Resources\Shops\Tables;

use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('city')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('plan')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('commission_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn (Shop $record) => $record->commissionLabel())
                    ->alignEnd(),
                TextColumn::make('jobs_count')->counts('jobs')->label('Jobs')->alignEnd(),
                TextColumn::make('rating')->numeric(decimalPlaces: 1)->sortable()->alignEnd(),
                TextColumn::make('created_at')->since()->label('Joined')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),
                SelectFilter::make('plan')
                    ->options([
                        'starter' => 'Starter',
                        'growth' => 'Growth',
                        'pro' => 'Pro',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Shop $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Shop $record): void {
                        $record->update(['status' => 'active']);

                        Notification::make()->title($record->name.' approved')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('name');
    }
}
