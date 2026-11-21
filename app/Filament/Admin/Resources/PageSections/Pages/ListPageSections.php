<?php

namespace App\Filament\Admin\Resources\PageSections\Pages;

use App\Filament\Admin\Resources\PageSections\PageSectionResource;
use App\Models\PageSection;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The site content screen from the prototype.
 *
 * Sections are not a table of rows so much as a list of the storefront's parts,
 * so the screen shows what each one is for (its key), whether it is live, and
 * when it last changed. Editing stays in the resource.
 */
class ListPageSections extends ListRecords
{
    protected static string $resource = PageSectionResource::class;

    protected string $view = 'filament.admin.resources.pagesections.pages.list-page-sections';

    public string $q = '';

    /** '', 'published', 'draft', 'hidden'. */
    public string $status = '';

    /** @return Collection<int, PageSection> */
    public function sections(): Collection
    {
        return $this->query()->get();
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        return PageSection::query()
            ->when($this->q !== '', function (Builder $query): void {
                $term = '%'.trim($this->q).'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('label', 'like', $term)
                    ->orWhere('key', 'like', $term));
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('sort');
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return [
            '' => 'All statuses',
            'published' => 'Published',
            'draft' => 'Draft',
            'hidden' => 'Hidden',
        ];
    }

    public function pillFor(string $status): string
    {
        return match ($status) {
            'published' => 'paid',
            'draft' => 'pending',
            'hidden' => 'cancel',
            default => 'pending',
        };
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = '';
    }

    public function hasFilters(): bool
    {
        return $this->q !== '' || $this->status !== '';
    }

    /** The first editable string in the section, so the row shows what it says. */
    public function preview(PageSection $section): string
    {
        $content = is_array($section->content) ? $section->content : [];

        foreach (['headline', 'title', 'text', 'blurb', 'eyebrow'] as $key) {
            if (filled($content[$key] ?? null) && is_string($content[$key])) {
                return $content[$key];
            }
        }

        return count($content).' fields';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New section'),
        ];
    }
}
