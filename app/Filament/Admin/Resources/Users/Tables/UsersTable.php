<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function roleColor(string $role): string
    {
        return match ($role) {
            'admin' => 'danger',
            'shop_owner' => 'warning',
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
                    ->description(fn (User $record): string => $record->email),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Administrator',
                        'shop_owner' => 'Shop owner',
                        default => 'Customer',
                    })
                    ->color(fn (string $state): string => self::roleColor($state)),

                TextColumn::make('shops.name')
                    ->label('Shops')
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('jobs_count')
                    ->label('Jobs')
                    ->state(fn (User $record): string => (string) $record->jobs()->count())
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('city')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'customer' => 'Customer',
                        'shop_owner' => 'Shop owner',
                        'admin' => 'Administrator',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
