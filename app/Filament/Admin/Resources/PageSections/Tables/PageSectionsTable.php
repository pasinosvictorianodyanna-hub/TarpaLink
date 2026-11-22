<?php

namespace App\Filament\Admin\Resources\PageSections\Tables;

use App\Models\PageSection;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * A native Filament table.
 *
 * This screen used to be a hand-written Blade table, which meant the actions
 * declared here never rendered and the screen looked like part of the
 * storefront rather than part of the CMS. Filament's own table is the point:
 * search, filters, sorting, bulk selection and per-row actions come with it,
 * and it behaves like every other admin screen.
 */
class PageSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('sort')
                    ->label('#')
                    ->sortable()
                    ->width(48),

                TextColumn::make('label')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('content')
                    ->label('What it says')
                    ->state(fn (PageSection $record): string => self::preview($record))
                    ->limit(40)
                    // Wrapped, not truncated: an unwrapped column sizes itself to
                    // its longest value, which pushes the row actions off screen.
                    ->wrap()
                    ->color('gray')
                    ->tooltip(fn (PageSection $record): string => self::preview($record))
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Edited')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'hidden' => 'Hidden',
                    ]),
            ])
            ->recordActions([
                Action::make('viewOnSite')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->tooltip('Open the page this section feeds')
                    ->url(fn (PageSection $record): string => $record->publicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (PageSection $record): bool => $record->status === 'published'),

                Action::make('publish')
                    ->label('Publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PageSection $record): bool => $record->status !== 'published')
                    ->action(fn (PageSection $record) => self::setStatus($record, 'published')),

                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Take this section off the site?')
                    ->modalDescription('It stops appearing immediately. The copy is kept, so publishing it again restores it.')
                    ->visible(fn (PageSection $record): bool => $record->status === 'published')
                    ->action(fn (PageSection $record) => self::setStatus($record, 'draft')),

                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkAction::make('publishSelected')
                    ->label('Publish selected')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $records->each->update(['status' => 'published']);

                        Notification::make()
                            ->success()
                            ->title($records->count().' '.str('section')->plural($records->count()).' published')
                            ->send();
                    }),

                BulkAction::make('unpublishSelected')
                    ->label('Unpublish selected')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('They come off the storefront. Their copy is kept.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $records->each->update(['status' => 'draft']);

                        Notification::make()
                            ->warning()
                            ->title($records->count().' '.str('section')->plural($records->count()).' unpublished')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No sections')
            ->emptyStateDescription('Run the seeder to create the default sections.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }

    private static function setStatus(PageSection $record, string $status): void
    {
        $record->update(['status' => $status]);

        Notification::make()
            ->success()
            ->title($record->label.' '.($status === 'published' ? 'published' : 'unpublished'))
            ->body($status === 'published'
                ? 'It is on the storefront now.'
                : 'It is off the storefront until you publish it again.')
            ->send();
    }

    /** The first editable string in the section, so a row shows what it actually says. */
    private static function preview(PageSection $record): string
    {
        $content = is_array($record->content) ? $record->content : [];

        foreach (['headline', 'title', 'text', 'blurb', 'eyebrow'] as $key) {
            if (filled($content[$key] ?? null) && is_string($content[$key])) {
                return $content[$key];
            }
        }

        return count($content).' fields';
    }
}
