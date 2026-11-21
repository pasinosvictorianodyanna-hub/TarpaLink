<?php

namespace App\Filament\Admin\Resources\Shops\Schemas;

use App\Filament\Admin\Resources\Shops\Tables\ShopsTable;
use App\Models\Shop;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shop')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold'),

                        TextEntry::make('slug')
                            ->fontFamily('mono')
                            ->size('sm'),

                        TextEntry::make('city'),

                        TextEntry::make('address')
                            ->columnSpan(2),

                        TextEntry::make('phone')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->placeholder('No description.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Admission')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => ShopsTable::statusColor($state)),

                        TextEntry::make('approved_at')
                            ->label('Approved')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not approved yet'),

                        TextEntry::make('approver.name')
                            ->label('Approved by')
                            ->placeholder('—'),

                        TextEntry::make('status_reason')
                            ->label('Reason on file')
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),

                Section::make('Commercials')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('planDefinition.name')
                            ->label('Plan')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),

                        TextEntry::make('commission_rate')
                            ->label('Take rate')
                            ->state(fn (Shop $record): string => $record->commissionLabel()),

                        TextEntry::make('gross')
                            ->label('Gross taken')
                            ->state(fn (Shop $record): string => Money::format(
                                (int) $record->payments()->where('payments.status', 'paid')->sum('payments.amount')
                            )),

                        TextEntry::make('commission_earned')
                            ->label('Commission earned')
                            ->state(fn (Shop $record): string => Money::format(
                                (int) $record->payments()->where('payments.status', 'paid')->sum('payments.commission')
                            )),

                        TextEntry::make('jobs_count')
                            ->label('Jobs')
                            ->state(fn (Shop $record): string => (string) $record->jobs()->count()),
                    ]),

                Section::make('Team')
                    ->schema([
                        TextEntry::make('staff')
                            ->hiddenLabel()
                            ->state(fn (Shop $record): array => $record->staff->map(
                                fn ($user) => sprintf('%s · %s · %s', $user->name, $user->email, ucfirst($user->pivot->role))
                            )->all() ?: ['No one is attached to this shop yet.'])
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }
}
