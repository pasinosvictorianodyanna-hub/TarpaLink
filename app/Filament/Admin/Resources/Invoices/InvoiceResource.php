<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\Shop;
use App\Services\Billing;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Subscription invoices, and the only place they are ever collected.
 *
 * TarpaLink does not hold a card for a shop, so there is nothing to charge. A
 * shop pays by GCash or a bank transfer and an administrator records it here —
 * which is why the action stamps `paid_by`. The alternative, pretending to
 * auto-charge, would produce invoices marked paid by nobody.
 *
 * **Overdue is not a status.** It is `open` plus a past `due_at`, computed in the
 * badge, so it is true the moment the deadline passes instead of the next time a
 * nightly job runs.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'invoice';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['shop', 'paidBy']);
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
                TextColumn::make('number')
                    ->label('Invoice')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shop.name')
                    ->label('Shop')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label('Period')
                    ->state(fn (Invoice $record) => $record->periodLabel())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Invoice $record) => match (true) {
                        $record->isOverdue() => 'Overdue',
                        $state === Invoice::PAID => 'Paid',
                        $state === Invoice::VOID => 'Void',
                        default => 'Open',
                    })
                    ->color(fn (string $state, Invoice $record) => match (true) {
                        $record->isOverdue() => 'danger',
                        $state === Invoice::PAID => 'success',
                        $state === Invoice::VOID => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('paidBy.name')
                    ->label('Recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Invoice::OPEN => 'Open',
                        Invoice::PAID => 'Paid',
                        Invoice::VOID => 'Void',
                    ])
                    ->default(Invoice::OPEN),

                SelectFilter::make('shop')
                    ->label('Shop')
                    ->options(fn () => Shop::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('invoices.shop_id', $data['value'])
                        : $query),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Record payment')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalHeading('Record this payment?')
                    ->modalDescription(fn (Invoice $record) => sprintf(
                        '%s from %s. This records that the money arrived; it does not move any.',
                        Money::format($record->amount),
                        $record->shop?->name ?? 'the shop',
                    ))
                    ->action(function (Invoice $record): void {
                        try {
                            app(Billing::class)->recordPayment($record, auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Not recorded')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($record->number.' marked paid')
                            ->success()
                            ->send();
                    }),

                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-m-x-circle')
                    ->color('gray')
                    ->visible(fn (Invoice $record) => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalHeading('Void this invoice?')
                    ->modalDescription('It stays on the ledger and will never be collected. The next run of billing:issue will re-bill the period.')
                    ->action(function (Invoice $record): void {
                        try {
                            app(Billing::class)->void($record);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Not voided')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($record->number.' voided')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('issued_at', 'desc')
            ->emptyStateHeading('No invoices')
            ->emptyStateDescription('Subscription invoices are issued by the nightly billing run for every active paid plan.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
