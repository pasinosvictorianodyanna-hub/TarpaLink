<?php

namespace App\Filament\Shop\Resources\Jobs\Schemas;

use App\Models\Job;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Reference')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (Job $record): string => $record->statusLabel())
                            ->color(fn (Job $record): string => \App\Filament\Shop\Resources\Jobs\Tables\JobsTable::statusColor($record->status)),

                        TextEntry::make('due_at')
                            ->label('Due')
                            ->date('d M Y')
                            ->placeholder('—'),

                        TextEntry::make('user.name')
                            ->label('Customer'),

                        TextEntry::make('user.phone')
                            ->label('Phone')
                            ->placeholder('—'),

                        TextEntry::make('service.name')
                            ->label('Service'),
                    ]),

                Section::make('Specification')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('size')
                            ->label('Size')
                            ->state(fn (Job $record): string => ($record->spec['width_in'] ?? '?')
                                .' × '.($record->spec['height_in'] ?? '?').' in'),

                        TextEntry::make('qty')
                            ->label('Quantity')
                            ->state(fn (Job $record): string => (string) ($record->spec['qty'] ?? '—')),

                        TextEntry::make('matrix_version')
                            ->label('Price list')
                            ->formatStateUsing(fn ($state): string => "v{$state}"),

                        TextEntry::make('notes')
                            ->placeholder('No notes from the customer.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Priced lines')
                    ->schema([
                        TextEntry::make('lines')
                            ->hiddenLabel()
                            ->state(fn (Job $record): array => $record->lines->map(
                                fn ($line) => sprintf(
                                    '%s — %s × %s = %s',
                                    $line->label,
                                    rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.'),
                                    Money::format((int) $line->unit_price),
                                    Money::format((int) $line->amount),
                                )
                            )->all())
                            ->listWithLineBreaks(),

                        TextEntry::make('subtotal')
                            ->formatStateUsing(fn ($state): string => Money::format($state)),

                        TextEntry::make('discount')
                            ->formatStateUsing(fn ($state): string => $state > 0 ? '−'.Money::format($state) : '—'),

                        TextEntry::make('total')
                            ->formatStateUsing(fn ($state): string => Money::format($state))
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Section::make('History')
                    ->schema([
                        TextEntry::make('activities')
                            ->hiddenLabel()
                            ->state(fn (Job $record): array => $record->activities->map(
                                fn ($a) => sprintf(
                                    '%s · %s → %s%s',
                                    $a->created_at->format('d M Y H:i'),
                                    $a->from_status ? (Job::STATUS_LABELS[$a->from_status] ?? $a->from_status) : 'created',
                                    Job::STATUS_LABELS[$a->to_status] ?? $a->to_status,
                                    $a->user ? ' · '.$a->user->name : '',
                                )
                            )->all() ?: ['No status changes yet.'])
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }
}
