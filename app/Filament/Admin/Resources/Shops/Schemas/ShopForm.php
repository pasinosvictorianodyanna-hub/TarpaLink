<?php

namespace App\Filament\Admin\Resources\Shops\Schemas;

use App\Models\Plan;
use App\Services\Geocoder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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

                Section::make('Location')
                    ->columns(2)
                    ->description('Where the shop sits on the customer map. A shop with no coordinates still appears in the list, but it can never be "nearest" to anybody — so this is worth filling in.')
                    ->schema([
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.0000001)
                            ->placeholder('14.6760'),

                        TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.0000001)
                            ->placeholder('121.0437'),

                        Actions::make([
                            Action::make('geocodeAddress')
                                ->label('Find coordinates from the address')
                                ->icon(Heroicon::OutlinedMapPin)
                                ->color('gray')
                                ->disabled(fn (Get $get): bool => blank($get('address')))
                                ->tooltip(fn (Get $get): ?string => blank($get('address'))
                                    ? 'Fill in the address above first.'
                                    : null)
                                ->action(function (Get $get, Set $set): void {
                                    // Address and city together, because
                                    // "Katipunan Ave" alone is a street in
                                    // several cities.
                                    $address = trim(implode(', ', array_filter([
                                        (string) $get('address'),
                                        (string) $get('city'),
                                    ])));

                                    $hit = app(Geocoder::class)->lookup($address);

                                    if ($hit === null) {
                                        Notification::make()
                                            ->title('Could not find that address')
                                            ->body('Try adding the city, or type the coordinates in by hand.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $set('lat', $hit['lat']);
                                    $set('lng', $hit['lng']);

                                    Notification::make()
                                        ->title('Coordinates found')
                                        ->body($hit['label'])
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
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
                            // Read-only because the rate *is* the plan. It was
                            // editable while the section described itself as a
                            // rate that "can never drift from the plan it came
                            // from" — so the field was the one thing able to
                            // break that promise. A bespoke rate now needs a
                            // plan, which is the only shape billing understands.
                            ->readOnly()
                            ->helperText('Set by the plan: 300 = 3.00%. Changing the plan changes this. Settled payouts keep the rate they were charged at.'),

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
