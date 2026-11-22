<?php

namespace App\Filament\Admin\Actions;

use App\Filament\Admin\Resources\ShopRequests\ShopRequestResource;
use App\Models\ShopRequest;
use App\Services\ShopOnboarding;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The two decisions an administrator can make about an application.
 *
 * Approving does real work — an account, a shop, a subscription, an invitation —
 * so it is one action with one transaction behind it, not a status field the
 * administrator sets and then has to remember to follow up on.
 */
final class ShopRequestActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve and create the account')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (ShopRequest $record): bool => $record->isNew())
            ->requiresConfirmation()
            ->modalHeading('Approve this shop?')
            ->modalDescription(fn (ShopRequest $record): string => sprintf(
                'This creates a shop-owner account for %s and a live shop called %s on the free Starter plan, then sends them a link to set their password.',
                $record->email,
                $record->business_name,
            ))
            ->modalSubmitActionLabel('Approve and create')
            ->action(function (ShopRequest $record) {
                try {
                    $result = app(ShopOnboarding::class)->approve($record, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Nothing was created')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->persistent()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title($result['shop']->name.' is live')
                    ->body($result['sent']
                        ? 'Invitation emailed to '.$result['user']->email.'.'
                        : 'Mail is not configured here, so nothing was emailed — copy the link on this page.')
                    ->success()
                    ->send();

                // The link is shown on the page rather than in a toast: a toast
                // disappears, and when the mailer is `log` this link is the only
                // way the owner will ever get in.
                session()->flash('invite_link', $result['link']);
                session()->flash('invite_email', $result['user']->email);
                session()->flash('invite_sent', $result['sent']);

                return redirect(ShopRequestResource::getUrl('view', ['record' => $record]));
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->visible(fn (ShopRequest $record): bool => $record->isNew())
            ->schema([
                \Filament\Forms\Components\Textarea::make('reason')
                    ->label('Why')
                    ->required()
                    ->maxLength(500)
                    ->helperText('Stored on the request. The applicant is not emailed automatically.'),
            ])
            ->action(function (ShopRequest $record, array $data): void {
                $record->forceFill([
                    'status' => 'rejected',
                    'reason' => $data['reason'],
                    'handled_by' => auth()->id(),
                    'handled_at' => now(),
                ])->save();

                Notification::make()
                    ->title('Request rejected')
                    ->body('Nothing was created.')
                    ->send();
            });
    }
}
