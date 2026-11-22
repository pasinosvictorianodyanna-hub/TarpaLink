<?php

namespace App\Filament\Admin\Actions;

use App\Filament\Admin\Resources\ShopRequests\ShopRequestResource;
use App\Models\ShopRequest;
use App\Services\ShopOnboarding;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * What an administrator can do about an application: decide it, or hand the
 * account over again.
 *
 * Approving does real work — an account, a shop, a subscription, an invitation —
 * so it is one action with one transaction behind it, not a status field the
 * administrator sets and then has to remember to follow up on. Resending is the
 * follow-up, for when the invitation never arrived or its hour ran out.
 */
final class ShopRequestActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(fn (ShopRequest $record): string => $record->hasAccount()
                ? 'Approve and open the shop'
                : 'Approve and create the account')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (ShopRequest $record): bool => $record->isNew())
            ->requiresConfirmation()
            ->modalHeading('Approve this shop?')
            ->modalDescription(fn (ShopRequest $record): string => $record->hasAccount()
                ? sprintf(
                    'This creates a live shop called %s on the free Starter plan and attaches it to %s, who already has a password. Nothing is emailed.',
                    $record->business_name,
                    $record->email,
                )
                : sprintf(
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
                    ->body($result['invited']
                        ? ($result['sent']
                            ? 'Invitation emailed to '.$result['user']->email.'.'
                            : 'Mail is not configured here, so nothing was emailed — copy the link on this page.')
                        : $result['user']->name.' signs in with the password they chose. Nothing was emailed.')
                    ->success()
                    ->send();

                // The link is shown on the page rather than in a toast: a toast
                // disappears, and when the mailer is `log` this link is the only
                // way the owner will ever get in.
                if ($result['link'] !== null) {
                    session()->flash('invite_link', $result['link']);
                    session()->flash('invite_email', $result['user']->email);
                    session()->flash('invite_sent', $result['sent']);
                }

                return redirect(ShopRequestResource::getUrl('view', ['record' => $record]));
            });
    }

    /**
     * A second chance at the handover.
     *
     * Approving flashes the link once. If the administrator closes the page, or
     * the token's hour runs out, the owner is locked out of a shop that exists —
     * and the stored token is hashed, so nobody can read it back. This is the
     * only recovery path that does not involve editing the database by hand.
     */
    public static function resend(): Action
    {
        return Action::make('resend')
            ->label('Resend invitation')
            ->icon('heroicon-m-envelope')
            ->color('gray')
            ->visible(fn (ShopRequest $record): bool => $record->status === 'approved'
                && $record->shop_id !== null
                // An applicant who registered chose their own password, so there
                // is no invitation to re-send — only the legacy path has one.
                && ! $record->hasAccount())
            ->requiresConfirmation()
            ->modalHeading('Send a new invitation?')
            ->modalDescription(fn (ShopRequest $record): string => sprintf(
                'This mints a fresh set-password link for %s and emails it. The previous link stops working, and nothing else about the shop changes.',
                $record->email,
            ))
            ->modalSubmitActionLabel('Send a new link')
            ->action(function (ShopRequest $record) {
                try {
                    $result = app(ShopOnboarding::class)->reinvite($record);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Nothing was sent')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->persistent()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title('New invitation for '.$result['shop']->name)
                    ->body($result['sent']
                        ? 'Emailed to '.$result['user']->email.'.'
                        : 'Mail is not configured here, so nothing was emailed — copy the link on this page.')
                    ->success()
                    ->send();

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
