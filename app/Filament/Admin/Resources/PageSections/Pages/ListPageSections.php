<?php

namespace App\Filament\Admin\Resources\PageSections\Pages;

use App\Filament\Admin\Resources\PageSections\PageSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * The site content screen.
 *
 * This class deliberately does almost nothing. It used to override `$view` with
 * a hand-written Blade table, which threw away every `recordActions()` the table
 * class declared and made the CMS look like part of the storefront rather than
 * part of the admin. The table, its filters, its publish/unpublish actions and
 * its bulk selection now come from PageSectionsTable; this adds the button to
 * create a section and nothing else.
 */
class ListPageSections extends ListRecords
{
    protected static string $resource = PageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New section'),
        ];
    }
}
