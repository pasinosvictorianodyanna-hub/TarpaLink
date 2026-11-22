<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The policy already refuses this when money is involved, and a
            // refused action simply vanishes. Left at that, an administrator
            // would conclude the panel had lost a feature. Disabled plus a
            // reason says why the account is permanent.
            DeleteAction::make()
                ->disabled(fn (User $record): bool => $record->jobs()->whereHas('payment')->exists())
                ->tooltip(fn (User $record): ?string => $record->jobs()->whereHas('payment')->exists()
                    ? 'This account has jobs with payments against them. Deleting it would erase the record of money moving.'
                    : null),
        ];
    }
}
