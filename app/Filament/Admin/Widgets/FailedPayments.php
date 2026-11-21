<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PaymentWebhook;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Callbacks that never settled after three attempts.
 *
 * A payment that fails silently is worse than one that fails loudly: the job
 * sits in "pending" forever and nobody notices. See docs/ISSUES.md #11.
 */
class FailedPayments extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PaymentWebhook::where('status', 'failed')->where('attempts', '>=', 3)->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Payments that never settled')
            ->description('Three failed callback attempts. These jobs are still marked unpaid.')
            ->query(fn (): Builder => PaymentWebhook::query()
                ->where('status', 'failed')
                ->where('attempts', '>=', 3)
                ->latest())
            ->columns([
                TextColumn::make('intent_id')->label('Intent')->fontFamily('mono'),
                TextColumn::make('type')->label('Event'),
                TextColumn::make('attempts')->badge()->color('danger'),
                TextColumn::make('error')->limit(60)->wrap(),
                TextColumn::make('created_at')->since()->label('First seen'),
            ])
            ->paginated(false);
    }
}
