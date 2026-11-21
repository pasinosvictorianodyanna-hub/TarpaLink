<?php

namespace App\Filament\Shop\Resources\QuantityDiscounts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuantityDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('min_qty')
                    ->label('When quantity reaches')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Bands apply at or above this quantity, on the whole job.'),

                TextInput::make('percent')
                    ->label('Discount')
                    ->suffix('%')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(90)
                    ->required(),
            ]);
    }
}
