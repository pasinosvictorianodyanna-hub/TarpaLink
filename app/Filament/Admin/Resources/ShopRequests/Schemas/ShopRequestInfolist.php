<?php

namespace App\Filament\Admin\Resources\ShopRequests\Schemas;

use App\Models\ShopRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * The invitation link, shown once, right after approval — or
                 * after a resend.
                 *
                 * It is a session flash rather than a stored column: the link is
                 * a password-reset token, and keeping it on the record would mean
                 * a live credential sitting in the database. When the mailer is
                 * `log` — which is this deployment's default — this box is the
                 * only way the owner will ever receive it, so it is at the top
                 * of the page rather than buried.
                 */
                Section::make('Invitation')
                    ->visible(fn (): bool => session()->has('invite_link'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('invite_sent')
                            ->label('Emailed to the owner')
                            ->badge()
                            ->state(fn (): string => session('invite_sent') ? 'Sent' : 'Not sent')
                            ->color(fn (): string => session('invite_sent') ? 'success' : 'warning'),

                        TextEntry::make('invite_email')
                            ->label('Address')
                            ->state(fn (): ?string => session('invite_email')),

                        TextEntry::make('invite_link')
                            ->label('Set-password link')
                            ->state(fn (): ?string => session('invite_link'))
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('sm')
                            ->columnSpanFull()
                            ->helperText('Copy this and send it to the owner if no email arrived. It works once and expires in an hour.'),
                    ]),

                Section::make('The request')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('business_name')
                            ->label('Business')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (ShopRequest $record): string => $record->statusLabel())
                            ->color(fn (string $state): string => match ($state) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'warning',
                            }),

                        TextEntry::make('contact_name')
                            ->label('Contact'),

                        TextEntry::make('email')
                            ->copyable(),

                        // Which of the two admission shapes this is. It changes
                        // what approving does: attach a shop to an account that
                        // exists, or create the account and hand over a link.
                        TextEntry::make('applicant.email')
                            ->label('Account')
                            ->placeholder('None — approval creates one')
                            ->helperText(fn (ShopRequest $record): string => $record->hasAccount()
                                ? 'Registered as they applied, and chose their own password. Approving only opens the shop.'
                                : 'No account yet. Approving creates one and emails a set-password link.'),

                        TextEntry::make('phone')
                            ->placeholder('—'),

                        TextEntry::make('city'),

                        TextEntry::make('address')
                            ->columnSpanFull(),

                        TextEntry::make('services')
                            ->label('Services they want to list')
                            ->state(fn (ShopRequest $record): string => implode("\n", $record->serviceLines()) ?: '—')
                            ->columnSpanFull(),

                        TextEntry::make('message')
                            ->placeholder('No note.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Decision')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Asked')
                            ->dateTime('d M Y, H:i'),

                        TextEntry::make('handled_at')
                            ->label('Handled')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('Still waiting'),

                        TextEntry::make('handler.name')
                            ->label('By')
                            ->placeholder('—'),

                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('shop.name')
                            ->label('Became')
                            ->placeholder('No shop yet')
                            ->url(fn (ShopRequest $record): ?string => $record->shop
                                ? \App\Filament\Admin\Resources\Shops\ShopResource::getUrl('view', ['record' => $record->shop])
                                : null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
