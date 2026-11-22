<?php

namespace App\Filament\Admin\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payment;
use App\Models\Shop;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Releasing money to shops.
 *
 * The ledger is `payments` — amount, commission and net live on one row, so
 * there is nothing to reconcile. This screen only moves `payout_status` from
 * pending to paid and stamps the date; it never touches an amount. See #18 for
 * why the ledger is a column and not a second table.
 */
class PayoutResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'payout';

    /** Only captured money is owed to anyone. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->settled()
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

                TextColumn::make('net')
                    ->label('Net to shop')
                    ->alignEnd()
                    ->weight('semibold')
                    ->state(fn (Payment $record) => Money::format($record->net())),

                TextColumn::make('payout_status')
                    ->label('Payout')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'paid' ? 'Paid out' : 'Pending')
                    ->color(fn (string $state) => $state === 'paid' ? 'success' : 'warning'),

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
                    ->visible(fn (Payment $record) => ! $record->isSettled())
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
                            if ($payment->isSettled()) {
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
