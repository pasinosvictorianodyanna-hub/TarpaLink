<?php

namespace App\Filament\Shop\Resources\Services\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Used in the storefront URL. Changing it breaks old links.'),

                Select::make('unit')
                    ->options([
                        'sqft' => 'Per square foot (tarpaulin)',
                        'pc' => 'Per piece (stickers, flyers, shirts)',
                    ])
                    ->default('sqft')
                    ->required()
                    ->native(false)
                    ->helperText('Decides whether a tier rate is multiplied by area or by quantity.'),

                Textarea::make('blurb')
                    ->rows(2)
                    ->columnSpanFull(),

                TextInput::make('sort')
                    ->label('Order')
                    ->numeric()
                    ->default(0),

                Toggle::make('active')
                    ->default(true)
                    ->helperText('Inactive services disappear from the storefront but keep their history.'),
            ]);
    }
}
