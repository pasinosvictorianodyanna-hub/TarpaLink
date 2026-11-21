<?php

namespace App\Filament\Admin\Resources\PageSections\Pages;

use App\Filament\Admin\Resources\PageSections\PageSectionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPageSection extends EditRecord
{
    protected static string $resource = PageSectionResource::class;

    /**
     * No delete action. Removing a row would break the Blade view that expects
     * the key; hiding it is the supported way to take a section off the site.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('hide')
                ->label('Hide from site')
                ->icon('heroicon-m-eye-slash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('The section stops rendering on the storefront. Nothing is deleted — publish it again any time.')
                ->visible(fn () => $this->record->status !== 'hidden')
                ->action(function (): void {
                    $this->record->update(['status' => 'hidden']);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Section hidden')
                        ->success()
                        ->send();
                }),
        ];
    }
}
