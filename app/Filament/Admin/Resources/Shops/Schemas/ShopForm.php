<?php

namespace App\Filament\Admin\Resources\Shops\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the storefront URL.'),

                        TextInput::make('city')
                            ->required()
                            ->maxLength(80),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(40),

                        TextInput::make('address')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Plan and take rate')
                    ->columns(2)
                    ->description('Commission is stored in basis points so it can never drift from the plan it came from.')
                    ->schema([
                        Select::make('plan')
                            ->options(fn (): array => Plan::orderBy('sort')->pluck('name', 'code')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('commission_rate', Plan::where('code', $state)->value('commission_rate'));
                            }),

                        TextInput::make('commission_rate')
                            ->label('Commission (basis points)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(10000)
                            ->helperText('300 = 3.00%. Changing this does not touch payouts already released.'),

                        Select::make('accent')
                            ->options([
                                'ink' => 'Ink (black)',
                                'magenta' => 'Magenta',
                                'cyan' => 'Cyan',
                                'yellow' => 'Yellow',
                            ])
                            ->default('ink'),
                    ]),

                Section::make('Admission')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending review',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->helperText('Use the approve / reject buttons for the normal path — this is the manual override.'),

                        TextInput::make('status_reason')
                            ->label('Reason on file')
                            ->maxLength(500)
                            ->placeholder('Shown to the shop when suspended'),
                    ]),
            ]);
    }
}
