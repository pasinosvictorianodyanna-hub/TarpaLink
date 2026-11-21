<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Payment;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payments whose webhook never landed.
 *
 * Issue #11 requires that a failed webhook is visible rather than leaving a job
 * quietly stuck in pending — this is that visibility.
 */
class PaymentsNeedingAttention extends TableWidget
{
    protected static ?string $heading = 'Payments needing attention';

    /** First thing on the dashboard when it exists: money that did not land. */
    protected static ?int $sort = 0;

    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Payment::needsAttention()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::needsAttention()->with('job.shop'))
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('job.reference')
                    ->label('Job')
                    ->weight('medium'),

                TextColumn::make('job.shop.name')
                    ->label('Shop'),

                TextColumn::make('intent_id')
                    ->label('Intent')
                    ->fontFamily('mono')
                    ->size('sm'),

                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),

                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->badge()
                    ->color(fn ($state): string => $state >= 3 ? 'danger' : 'warning'),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(60)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->emptyStateHeading('Nothing needs attention');
    }
}
