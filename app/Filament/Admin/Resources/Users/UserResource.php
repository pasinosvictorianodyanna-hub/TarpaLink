<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * Four roles, one table. See docs/ISSUES.md #9.
 *
 * `users.role` decides which panel a person can open; the shop_users pivot
 * decides which shop they see once inside.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(32),
                    TextInput::make('city')->maxLength(80),
                ]),

            Section::make('Access')
                ->columns(2)
                ->schema([
                    Select::make('role')
                        ->options([
                            'customer' => 'Customer',
                            'shop_owner' => 'Shop owner',
                            'admin' => 'Administrator',
                        ])
                        ->required()
                        ->default('customer')
                        ->helperText('Shop owners also need a shop_users row before they can open /shop.'),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Leave blank to keep the current password.'
                            : null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'admin' => 'Administrator',
                        'shop_owner' => 'Shop owner',
                        default => 'Customer',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'shop_owner' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('jobs_count')->counts('jobs')->label('Jobs')->alignEnd(),
                TextColumn::make('created_at')->since()->label('Joined')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'customer' => 'Customer',
                        'shop_owner' => 'Shop owner',
                        'admin' => 'Administrator',
                    ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
