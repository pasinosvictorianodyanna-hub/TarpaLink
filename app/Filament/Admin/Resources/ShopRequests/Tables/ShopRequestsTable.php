<?php

namespace App\Filament\Admin\Resources\ShopRequests\Tables;

use App\Filament\Admin\Actions\ShopRequestActions;
use App\Models\ShopRequest;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShopRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')
                    ->label('Shop')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('city')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Asked')
                    ->since()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ShopRequest $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                // The queue is what needs answering; handled requests are
                // history and should be asked for, not scrolled past.
                SelectFilter::make('status')
                    ->options([
                        'new' => 'Waiting',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('new'),
            ])
            ->recordActions([
                ShopRequestActions::approve(),
                ShopRequestActions::reject(),
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('No shop requests')
            ->emptyStateDescription('Applications from /open-a-shop appear here, oldest first.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
