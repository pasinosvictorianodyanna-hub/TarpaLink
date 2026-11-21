<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The `shop_users` pivot, editable from the person's side.
 *
 * Two roles live here and nowhere else: `owner` and `staff`. `users.role` says
 * someone is a shop owner in general; this says which shop, and whether they can
 * change the price matrix or only work the board.
 */
class ShopsRelationManager extends RelationManager
{
    protected static string $relationship = 'shops';

    protected static ?string $title = 'Shop access';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium'),

                TextColumn::make('city'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('pivot.role')
                    ->label('Role in shop')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'owner' ? 'warning' : 'gray'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach a shop')
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options(['owner' => 'Owner', 'staff' => 'Staff'])
                            ->default('staff')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change role')
                    ->icon('heroicon-m-arrow-path')
                    ->schema([
                        Select::make('role')
                            ->options(['owner' => 'Owner', 'staff' => 'Staff'])
                            ->default(fn (Shop $record): string => $record->pivot->role)
                            ->required(),
                    ])
                    ->action(function (Shop $record, array $data): void {
                        $pivot = $record->pivot;
                        $pivot->role = $data['role'];
                        $pivot->save();
                    }),

                DetachAction::make()
                    ->label('Remove access'),
            ]);
    }
}
