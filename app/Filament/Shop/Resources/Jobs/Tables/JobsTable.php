<?php

namespace App\Filament\Shop\Resources\Jobs\Tables;

use App\Models\Job;
use App\Models\Proof;
use App\Services\JobWorkflow;
use App\Services\ProofExchange;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobsTable
{
    /** Badge colour per status. Red/amber only where a human is waiting on something. */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'quoted' => 'gray',
            'awaiting_proof' => 'warning',
            'approved' => 'info',
            'in_production' => 'primary',
            'ready' => 'success',
            'collected' => 'gray',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('service.name')
                    ->label('Service'),

                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Job $record): string => $record->statusLabel())
                    ->color(fn (Job $record): string => self::statusColor($record->status)),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date('d M')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Job::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::uploadProofAction(),
                self::advanceAction(),
            ]);
    }

    /**
     * Send the artwork for the customer to approve.
     *
     * Versioned rather than overwritten: rejecting a proof must not erase what
     * the customer saw, and the last row is what `Job::latestProof()` returns.
     * The file goes on the private disk and is served through a checked route.
     */
    public static function uploadProofAction(): Action
    {
        return Action::make('uploadProof')
            ->label('Upload proof')
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color('warning')
            ->visible(fn (Job $record): bool => in_array($record->status, ['quoted', 'awaiting_proof'], true))
            ->schema([
                FileUpload::make('file')
                    ->label('Proof artwork')
                    ->disk(Proof::DISK)
                    ->directory('proofs')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->previewable(false)
                    ->required(),
                Textarea::make('note')
                    ->label('Note to the customer')
                    ->nullable()
                    ->maxLength(500),
            ])
            ->action(function (Job $record, array $data): void {
                app(ProofExchange::class)->send(
                    $record,
                    $data['file'],
                    $data['note'] ?? null,
                    auth()->user(),
                );
            })
            ->successNotificationTitle('Proof sent to the customer');
    }

    /**
     * One action, offering only the statuses the workflow actually allows.
     * The same rules are re-checked server-side by JobWorkflow::transition().
     */
    public static function advanceAction(): Action
    {
        return Action::make('advance')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->visible(fn (Job $record): bool => JobWorkflow::allowedFrom($record->status) !== [])
            ->schema(fn (Job $record): array => [
                Select::make('status')
                    ->label('Move to')
                    ->options(collect(JobWorkflow::allowedFrom($record->status))
                        ->mapWithKeys(fn (string $s): array => [$s => Job::STATUS_LABELS[$s] ?? $s])
                        ->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (Job $record, array $data): void {
                JobWorkflow::transition($record, $data['status'], auth()->user());
            })
            ->successNotificationTitle('Job status updated');
    }
}
