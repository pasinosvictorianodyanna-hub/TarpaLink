<?php

namespace App\Filament\Shop\Resources\Services\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->weight('bold'),

                TextEntry::make('unit')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'sqft' ? 'per sq ft' : 'per piece'),

                TextEntry::make('blurb')
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('tiers')
                    ->label('Tiers')
                    ->state(fn ($record) => $record->tiers->map(
                        fn ($tier) => $tier->label.' · '.Money::format($tier->unit_price)
                    )->all())
                    ->listWithLineBreaks()
                    ->columnSpanFull(),

                TextEntry::make('options')
                    ->label('Finishing')
                    ->state(fn ($record) => $record->options->map(
                        fn ($option) => $option->name.' · '.Money::format($option->price).' / '.$option->unit
                    )->all())
                    ->listWithLineBreaks()
                    ->columnSpanFull(),

                TextEntry::make('active')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Active' : 'Hidden'),
            ]);
    }
}
