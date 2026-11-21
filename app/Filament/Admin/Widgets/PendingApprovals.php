<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queue an administrator actually opens the dashboard for.
 *
 * Approving sets shops.status = active, which is exactly what
 * User::canAccessPanel() checks before letting an owner into /shop.
 */
class PendingApprovals extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shops awaiting approval')
            ->query(fn (): Builder => Shop::query()->where('status', 'pending'))
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('city'),
                TextColumn::make('plan')->badge()->color('gray'),
                TextColumn::make('created_at')->since()->label('Applied'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this shop?')
                    ->modalDescription('The owner will be able to log in to the shop panel immediately.')
                    ->action(function (Shop $record): void {
                        $record->update(['status' => 'active']);

                        Notification::make()
                            ->title($record->name.' approved')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject this shop?')
                    ->modalDescription('The owner will not be able to log in. This can be reversed later.')
                    ->action(function (Shop $record): void {
                        $record->update(['status' => 'suspended']);

                        Notification::make()
                            ->title($record->name.' rejected')
                            ->warning()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nothing to approve')
            ->emptyStateDescription('New shop applications land here.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated(false);
    }
}
