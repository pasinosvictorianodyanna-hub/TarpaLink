<?php

namespace App\Filament\Admin\Resources\Plans;

use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The plan catalogue. Editing a rate here changes what *future* shops on that
 * plan are charged; shops already on it keep the rate copied onto their row until
 * someone upgrades them, and existing payouts never move.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(40),
            TextInput::make('tagline')->maxLength(120)->columnSpanFull(),

            TextInput::make('monthly_fee')
                ->label('Monthly fee (₱)')
                ->numeric()
                ->step('0.01')
                ->required()
                ->formatStateUsing(fn ($state) => $state === null ? null : number_format($state / 100, 2, '.', ''))
                ->dehydrateStateUsing(fn ($state) => Money::toCentavos($state)),

            TextInput::make('commission_rate')
                ->label('Commission (%)')
                ->numeric()
                ->step('0.01')
                ->required()
                ->helperText('Stored in basis points, so 1.5% stays exact.')
                ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format($state / 100, 2, '.', ''), '0'), '.'))
                ->dehydrateStateUsing(fn ($state) => Money::toCentavos($state)),

            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('semibold')->sortable(),
                TextColumn::make('monthly_fee')
                    ->label('Monthly')
                    ->formatStateUsing(fn (Plan $record) => $record->feeLabel())
                    ->alignEnd(),
                TextColumn::make('commission_rate')
                    ->label('Take rate')
                    ->formatStateUsing(fn (Plan $record) => $record->commissionLabel())
                    ->alignEnd(),
                TextColumn::make('subscriptions_count')->counts('subscriptions')->label('Shops')->alignEnd(),
            ])
            ->defaultSort('sort')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
