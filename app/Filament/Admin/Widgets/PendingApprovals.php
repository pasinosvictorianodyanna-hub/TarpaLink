<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Actions\ShopActions;
use App\Models\Shop;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shops that registered and are waiting to be let in.
 *
 * The widget disappears entirely when the queue is empty rather than showing an
 * empty table — an empty "needs you" panel is noise on a dashboard.
 */
class PendingApprovals extends TableWidget
{
    protected static ?string $heading = 'Waiting for approval';

    protected static ?int $sort = 2;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Shop::where('status', 'pending')->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Shop::query()
                ->where('status', 'pending')
                ->with('staff'))
            ->defaultSort('created_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (Shop $record): string => $record->address),

                TextColumn::make('city'),

                TextColumn::make('staff.name')
                    ->label('Owner')
                    ->listWithLineBreaks()
                    ->placeholder('No owner attached'),

                TextColumn::make('staff.email')
                    ->label('Contact')
                    ->listWithLineBreaks()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Waiting')
                    ->since(),
            ])
            ->recordActions([
                ShopActions::approve(),
                ShopActions::reject(),
            ]);
    }
}
