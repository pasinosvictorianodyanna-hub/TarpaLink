<?php

namespace App\Filament\Shop\Pages;

use App\Models\Payment;
use App\Models\Payout;
use App\Support\CsvExport;
use App\Support\Money;
use App\Support\ShopContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The payout ledger: what each captured payment earned the shop, what the
 * platform took, and what is left. See docs/ISSUES.md #7.
 */
class Payouts extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Payouts';

    protected string $view = 'filament.shop.pages.payouts';

    /** Captured this calendar month, straight from payments — the ledger must agree. */
    public int $monthGross = 0;

    public int $monthCommission = 0;

    public int $monthNet = 0;

    /** Everything earned but not yet paid out. */
    public int $pendingNet = 0;

    public string $monthLabel = '';

    public function mount(): void
    {
        $shop = app(ShopContext::class)->shop();

        abort_if($shop === null, 403);

        $this->monthLabel = now()->format('F Y');

        $paid = Payment::query()
            ->whereHas('job', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $this->monthGross = (int) (clone $paid)->sum('amount');
        $this->monthCommission = (int) (clone $paid)->sum('commission');
        $this->monthNet = $this->monthGross - $this->monthCommission;

        $this->pendingNet = (int) $shop->payouts()
            ->whereIn('status', ['pending', 'scheduled'])
            ->sum('net');
    }

    public function table(Table $table): Table
    {
        $shopId = app(ShopContext::class)->id();

        return $table
            ->query(fn (): Builder => Payout::query()
                ->where('shop_id', $shopId)
                ->with(['payment.job']))
            ->columns([
                TextColumn::make('created_at')->date('d M Y')->label('Captured')->sortable(),
                TextColumn::make('payment.job.reference')
                    ->label('Job')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('gross')
                    ->label('Gross')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextColumn::make('commission')
                    ->label('Commission')
                    ->alignEnd()
                    ->formatStateUsing(fn (Payout $record) => Money::format($record->commission).' ('.$record->commissionLabel().')'),
                TextColumn::make('net')
                    ->label('Net')
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
                TextColumn::make('payout_date')->date('d M Y')->label('Paid out')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'scheduled' => 'Scheduled',
                        'paid' => 'Paid out',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No payouts yet')
            ->emptyStateDescription('A payout row appears here each time a payment is captured.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $shopId = app(ShopContext::class)->id();

                    $rows = Payout::query()
                        ->where('shop_id', $shopId)
                        ->with('payment.job')
                        ->orderBy('created_at')
                        ->get()
                        ->map(fn (Payout $payout) => [
                            $payout->created_at?->toDateString(),
                            $payout->payment?->job?->reference,
                            number_format($payout->gross / 100, 2, '.', ''),
                            $payout->commissionLabel(),
                            number_format($payout->commission / 100, 2, '.', ''),
                            number_format($payout->net / 100, 2, '.', ''),
                            $payout->statusLabel(),
                            $payout->payout_date?->toDateString(),
                        ]);

                    return CsvExport::stream(
                        'tarpalink-payouts-'.now()->format('Y-m-d').'.csv',
                        ['Captured', 'Job', 'Gross (PHP)', 'Rate', 'Commission (PHP)', 'Net (PHP)', 'Status', 'Payout date'],
                        $rows,
                    );
                }),
        ];
    }
}
