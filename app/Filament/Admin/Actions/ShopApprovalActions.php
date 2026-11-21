<?php

namespace App\Filament\Admin\Actions;

use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Approve / reject, defined once.
 *
 * Both the dashboard queue and the shops table show these; two copies would drift
 * the moment one of them gained a reason field.
 */
final class ShopApprovalActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (Shop $record) => $record->status !== 'active')
            ->requiresConfirmation()
            ->modalHeading('Approve this shop?')
            ->modalDescription('The owner will be able to log in to the shop panel immediately.')
            ->action(function (Shop $record): void {
                // Approving clears any reason left by an earlier rejection.
                $record->update(['status' => 'active', 'status_note' => null]);

                Notification::make()
                    ->title($record->name.' approved')
                    ->success()
                    ->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->visible(fn (Shop $record) => $record->status !== 'suspended')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->helperText('Stored on the shop and shown to the owner. Required — a locked door needs a note on it.')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->modalHeading('Reject this shop?')
            ->modalDescription('The owner will not be able to log in. This can be reversed later.')
            ->action(function (Shop $record, array $data): void {
                $record->update([
                    'status' => 'suspended',
                    'status_note' => $data['reason'],
                ]);

                Notification::make()
                    ->title($record->name.' rejected')
                    ->body($data['reason'])
                    ->warning()
                    ->send();
            });
    }
}
