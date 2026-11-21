<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Platform fees invoiced to shops. Read-mostly: an invoice is a record of what
 * was billed, not a form. See docs/ISSUES.md #12.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Invoices';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->fontFamily('mono')->searchable()->weight('semibold'),
                TextColumn::make('shop.name')->label('Shop')->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record) => $record->statusLabel())
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'open' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('period_start')->date('M Y')->label('Period'),
                TextColumn::make('issued_at')->date('d M Y')->label('Issued'),
                TextColumn::make('paid_at')->date('d M Y')->label('Paid')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'paid' => 'Paid',
                        'void' => 'Void',
                    ]),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (Invoice $record): void {
                        $record->update(['status' => 'paid', 'paid_at' => now()]);

                        Notification::make()->title($record->number.' marked paid')->success()->send();
                    }),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
