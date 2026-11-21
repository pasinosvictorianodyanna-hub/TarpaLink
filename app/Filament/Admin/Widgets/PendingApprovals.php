<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Actions\ShopApprovalActions;
use App\Models\Shop;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queue an administrator actually opens the dashboard for.
 *
 * Approving sets shops.status = active, which is exactly what
 * User::canAccessPanel() checks before letting an owner into /shop.
 */
class PendingApprovals extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shops awaiting approval')
            ->query(fn (): Builder => Shop::query()->where('status', 'pending'))
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('city'),
                TextColumn::make('plan')->badge()->color('gray'),
                TextColumn::make('created_at')->since()->label('Applied'),
            ])
            ->recordActions([
                ShopApprovalActions::approve(),
                ShopApprovalActions::reject(),
            ])
            ->emptyStateHeading('Nothing to approve')
            ->emptyStateDescription('New shop applications land here.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated(false);
    }
}
