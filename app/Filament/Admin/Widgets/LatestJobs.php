<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Job;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The live feed: what is being ordered, across every shop, right now. */
class LatestJobs extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest jobs')
            ->query(fn (): Builder => Job::query()->with(['shop', 'service'])->latest()->limit(10))
            ->columns([
                TextColumn::make('reference')->fontFamily('mono')->weight('semibold'),
                TextColumn::make('shop.name')->label('Shop'),
                TextColumn::make('service.name')->label('Service'),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Job $record) => $record->statusLabel())
                    ->color(fn (Job $record) => match ($record->status) {
                        'collected' => 'success',
                        'cancelled' => 'danger',
                        'ready' => 'info',
                        'in_production', 'approved' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->since()->label('Placed'),
            ])
            ->paginated(false);
    }
}
