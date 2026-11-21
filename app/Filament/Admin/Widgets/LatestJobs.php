<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Job;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The latest work happening anywhere on the platform. */
class LatestJobs extends TableWidget
{
    protected static ?string $heading = 'Latest jobs';

    protected static ?int $sort = 4;

    /** Render inline: a dashboard of four widgets should not pop in after paint. */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Job::query()->with(['shop', 'user']))
            ->defaultSort('created_at', 'desc')
            ->paginated([8])
            ->columns([
                TextColumn::make('reference')
                    ->label('Job')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->weight('medium'),

                TextColumn::make('shop.name')
                    ->label('Shop'),

                TextColumn::make('user.name')
                    ->label('Customer'),

                TextColumn::make('service.name')
                    ->label('Service')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Job::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'collected' => 'success',
                        'cancelled' => 'danger',
                        'ready' => 'info',
                        'in_production' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Opened')
                    ->since(),
            ]);
    }
}
