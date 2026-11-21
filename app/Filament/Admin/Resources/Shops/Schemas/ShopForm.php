<?php

namespace App\Filament\Admin\Resources\Shops\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shop')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(120),
                        TextInput::make('slug')->required()->maxLength(120)->unique(ignoreRecord: true),
                        TextInput::make('city')->required()->maxLength(80),
                        TextInput::make('address')->required()->maxLength(160),
                        TextInput::make('phone')->tel()->maxLength(32),
                        Select::make('accent')
                            ->options([
                                'ink' => 'Ink (default)',
                                'magenta' => 'Magenta',
                                'cyan' => 'Cyan',
                                'yellow' => 'Yellow',
                            ])
                            ->required()
                            ->default('ink'),
                        Textarea::make('description')->rows(3)->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->columns(3)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending approval',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('pending')
                            ->helperText('Only active shops can log in to the shop panel.'),
                        TextInput::make('rating')->numeric()->minValue(0)->maxValue(5)->step(0.1)->default(0),
                        TextInput::make('jobs_done')->numeric()->minValue(0)->default(0),
                        Toggle::make('accepts_rush')->default(true),
                        TextInput::make('status_note')
                            ->label('Status reason')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Shown to the owner when the shop is not active. Cleared automatically on approval.'),
                    ]),

                Section::make('Commercial')
                    ->columns(2)
                    ->schema([
                        Select::make('plan')
                            ->options(fn () => Plan::orderBy('sort')->pluck('name', 'key')->all())
                            ->required()
                            ->helperText('Changing the plan also changes the commission rate.'),
                        Placeholder::make('commission_rate')
                            ->label('Commission rate')
                            ->content(fn ($record) => $record?->commissionLabel() ?? 'Set by the plan'),
                    ]),
            ]);
    }
}
