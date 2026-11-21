<?php

namespace App\Filament\Admin\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Models\Payout;
use App\Models\Shop;
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
 * The platform's side of the ledger. Rows are written by settlement; the only
 * thing an administrator does here is move them to scheduled or paid.
 */
class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Payouts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shop.name')->label('Shop')->searchable()->sortable(),
                TextColumn::make('payment.job.reference')->label('Job')->fontFamily('mono'),
                TextColumn::make('gross')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextColumn::make('commission')
                    ->alignEnd()
                    ->formatStateUsing(fn (Payout $record) => Money::format($record->commission).' ('.$record->commissionLabel().')'),
                TextColumn::make('net')
                    ->alignEnd()
                    ->weight('semibold')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Payout $record) => $record->statusLabel())
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('payout_date')->date('d M Y')->label('Payout date')->placeholder('—'),
                TextColumn::make('created_at')->date('d M Y')->label('Captured')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'scheduled' => 'Scheduled',
                        'paid' => 'Paid out',
                    ]),
                SelectFilter::make('shop_id')
                    ->label('Shop')
                    ->options(fn () => Shop::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('schedule')
                    ->label('Schedule')
                    ->icon('heroicon-m-calendar-days')
                    ->color('info')
                    ->visible(fn (Payout $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Payout $record): void {
                        $record->update([
                            'status' => 'scheduled',
                            'payout_date' => now()->addWeek()->toDateString(),
                        ]);

                        Notification::make()->title('Payout scheduled')->success()->send();
                    }),

                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Payout $record) => $record->status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function (Payout $record): void {
                        $record->update([
                            'status' => 'paid',
                            'payout_date' => now()->toDateString(),
                        ]);

                        Notification::make()
                            ->title(Money::format($record->net).' paid to '.$record->shop->name)
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
        ];
    }
}
