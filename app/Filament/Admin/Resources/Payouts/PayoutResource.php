<?php

namespace App\Filament\Admin\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\Refund;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Releasing money to shops, and sending it back to customers.
 *
 * The ledger is `payments` — amount, commission and net live on one row, so
 * there is nothing to reconcile. Settling only moves `payout_status` from
 * pending to paid and stamps the date; it never touches an amount. See #18 for
 * why the ledger is a column and not a second table.
 *
 * Refunds are the other direction, and they are not a status flip either: they
 * accumulate in `refunded_amount` and reverse the commission proportionally.
 * The arithmetic lives in `Refund`; this screen only collects the two things a
 * person has to decide — how much, and why.
 */
class PayoutResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'payout';

    /**
     * Money that moved, refunds included.
     *
     * A refunded payment has to stay reachable here: its net is negative — the
     * processor fee the shop bears — and an admin who cannot see the row cannot
     * explain the short payout, or refund the rest of a partial one.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->moved()
            ->with(['job.shop', 'job.service']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job.shop.name')
                    ->label('Shop')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('job.reference')
                    ->label('Job')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('paid_at')
                    ->label('Captured')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Gross')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),

                TextColumn::make('commission')
                    ->label('Commission')
                    ->alignEnd()
                    ->formatStateUsing(fn (Payment $record) => Money::format($record->commission)),

                // The processor's cut, not a platform charge. It sits here
                // because it is deducted before the shop is paid, and a payout
                // that cannot be reconciled line by line is not a ledger.
                TextColumn::make('processing_fee')
                    ->label('Fee')
                    ->alignEnd()
                    ->toggleable()
                    ->formatStateUsing(fn (Payment $record) => Money::format($record->processing_fee)),

                TextColumn::make('refunded_amount')
                    ->label('Refunded')
                    ->alignEnd()
                    ->toggleable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (Payment $record) => Money::format($record->refunded_amount)),

                TextColumn::make('net')
                    ->label('Net to shop')
                    ->alignEnd()
                    ->weight('semibold')
                    ->state(fn (Payment $record) => Money::format($record->net())),

                TextColumn::make('payout_status')
                    ->label('Payout')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Payment $record) => match (true) {
                        $record->isFullyRefunded() => 'Refunded',
                        $state === 'paid' => 'Paid out',
                        default => 'Pending',
                    })
                    ->color(fn (string $state, Payment $record) => match (true) {
                        $record->isFullyRefunded() => 'danger',
                        $state === 'paid' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('payout_date')
                    ->label('Payout date')
                    ->date('d M Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('payout_status')
                    ->label('Payout status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid out',
                    ])
                    ->default('pending'),

                SelectFilter::make('shop')
                    ->label('Shop')
                    ->options(fn () => Shop::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('job', fn ($q) => $q->where('shop_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('settle')
                    ->label('Mark paid out')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->authorize('settle')
                    // A refunded payment owes the shop nothing — its net is the
                    // fee we could not recover — so there is nothing to release.
                    ->visible(fn (Payment $record) => ! $record->isSettled() && ! $record->isFullyRefunded())
                    ->requiresConfirmation()
                    ->modalHeading('Release this payout?')
                    ->modalDescription(fn (Payment $record) => sprintf(
                        '%s to %s. This records that the platform paid the shop; it does not move money.',
                        Money::format($record->net()),
                        $record->job?->shop?->name ?? 'the shop',
                    ))
                    ->action(function (Payment $record): void {
                        $record->update([
                            'payout_status' => 'paid',
                            'payout_date' => now()->toDateString(),
                        ]);

                        Notification::make()
                            ->title(Money::format($record->net()).' released')
                            ->success()
                            ->send();
                    }),

                // Cancelling a job after the proof is approved refunds nothing
                // automatically, because the material is already committed. This
                // is where a person makes that call, in whole or in part.
                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === 'paid' && $record->remainingRefundable() > 0)
                    ->modalHeading('Refund this payment')
                    ->modalDescription(fn (Payment $record) => sprintf(
                        '%s remains refundable. The processor keeps its fee, so a full refund leaves %s against the shop — see #81.',
                        Money::format($record->remainingRefundable()),
                        Money::signed(-$record->processing_fee),
                    ))
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount (₱)')
                            ->numeric()
                            ->required()
                            ->default(fn (Payment $record) => $record->remainingRefundable() / 100)
                            ->rules([
                                fn (Payment $record) => function (string $attribute, $value, $fail) use ($record): void {
                                    $centavos = Money::toCentavos($value);

                                    if ($centavos > $record->remainingRefundable()) {
                                        $fail('That is more than the '.Money::format($record->remainingRefundable()).' left on this payment.');
                                    }
                                },
                            ]),

                        TextInput::make('reason')
                            ->label('Reason')
                            ->maxLength(255)
                            ->placeholder('Customer cancelled before printing'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            $refunded = app(Refund::class)->refund(
                                $record,
                                Money::toCentavos($data['amount']),
                                auth()->user(),
                                $data['reason'] ?? null,
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Not refunded')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(Money::format($refunded->refunded_amount).' refunded')
                            ->body('Net to shop on this payment is now '.Money::signed($refunded->net()).'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('settleSelected')
                    ->label('Mark selected as paid out')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Only rows still pending are changed. Already-paid rows keep their original date.')
                    ->action(function (Collection $records): void {
                        $settled = 0;

                        foreach ($records as $payment) {
                            // A refunded payment owes the shop nothing, so it is
                            // not "already paid" — it is not payable at all.
                            if ($payment->isSettled() || $payment->isFullyRefunded()) {
                                continue;
                            }

                            $payment->update([
                                'payout_status' => 'paid',
                                'payout_date' => now()->toDateString(),
                            ]);

                            $settled++;
                        }

                        Notification::make()
                            ->title($settled.' '.str('payout')->plural($settled).' released')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading('Nothing awaiting payout')
            ->emptyStateDescription('Captured payments that have not been released to their shop appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
        ];
    }
}
