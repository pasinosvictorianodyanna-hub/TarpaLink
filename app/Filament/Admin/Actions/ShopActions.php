<?php

namespace App\Filament\Admin\Actions;

use App\Models\Shop;
use App\Services\ShopApproval;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The admission buttons, defined once.
 *
 * They appear both in the pending-approvals queue and on the shop resource; if
 * they were defined twice the two copies would drift, and one of them would
 * eventually forget the reason field.
 */
final class ShopActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve this shop?')
            ->modalDescription('The shop goes live on the storefront and its owner can sign in to the shop panel.')
            ->visible(fn (Shop $record): bool => $record->isPending())
            ->action(function (Shop $record): void {
                app(ShopApproval::class)->approve($record, auth()->user());

                Notification::make()
                    ->success()
                    ->title("{$record->name} approved")
                    ->body('The owner can now sign in to the shop panel.')
                    ->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject this shop?')
            ->modalDescription('The owner is locked out of the shop panel and the reason below is stored on the shop.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason shown to the shop')
                    ->required()
                    ->rows(3)
                    ->maxLength(500)
                    ->placeholder('e.g. Business permit could not be verified.'),
            ])
            ->visible(fn (Shop $record): bool => $record->isPending())
            ->action(function (Shop $record, array $data): void {
                app(ShopApproval::class)->reject($record, $data['reason'], auth()->user());

                Notification::make()
                    ->warning()
                    ->title("{$record->name} rejected")
                    ->body('The owner cannot sign in to the shop panel.')
                    ->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspend this shop?')
            ->modalDescription('The storefront listing disappears immediately and the owner is locked out of the panel. Existing jobs stay where they are.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason shown to the shop')
                    ->required()
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->visible(fn (Shop $record): bool => $record->isActive())
            ->action(function (Shop $record, array $data): void {
                app(ShopApproval::class)->suspend($record, $data['reason'], auth()->user());

                Notification::make()
                    ->warning()
                    ->title("{$record->name} suspended")
                    ->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Send back to queue')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Shop $record): bool => ! $record->isPending())
            ->action(function (Shop $record): void {
                app(ShopApproval::class)->reopen($record, auth()->user());

                Notification::make()
                    ->info()
                    ->title("{$record->name} is back in the queue")
                    ->send();
            });
    }
}
