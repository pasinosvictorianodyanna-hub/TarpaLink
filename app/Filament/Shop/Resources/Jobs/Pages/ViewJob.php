<?php

namespace App\Filament\Shop\Resources\Jobs\Pages;

use App\Filament\Shop\Resources\Jobs\JobResource;
use App\Filament\Shop\Resources\Jobs\Tables\JobsTable;
use Filament\Resources\Pages\ViewRecord;

class ViewJob extends ViewRecord
{
    protected static string $resource = JobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            JobsTable::advanceAction(),
        ];
    }
}
