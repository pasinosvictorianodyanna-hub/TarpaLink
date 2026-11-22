<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Person')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(160)
                            ->unique(ignoreRecord: true),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(40),

                        TextInput::make('city')
                            ->maxLength(80),
                    ]),

                Section::make('Access')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->options([
                                'customer' => 'Customer',
                                'shop_owner' => 'Shop owner',
                                'staff' => 'Shop staff',
                                'admin' => 'Administrator',
                            ])
                            ->required()
                            ->helperText('Owners and staff also need a shop attached below before the shop panel opens. Staff work the queue and board; only owners can change prices and billing.'),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Leave empty to keep the current password.'),
                    ]),
            ]);
    }
}
