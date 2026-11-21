<?php

namespace App\Filament\Shop\Resources\Jobs\Pages;

use App\Filament\Shop\Resources\Jobs\JobResource;
use App\Models\Job;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The jobs screen from the prototype.
 *
 * Filament's table page is replaced by a view because the design is not a table
 * with different colours — it is a different row. The reference carries the
 * customer's contact details, the service carries its spec, and status is a
 * pill rather than a badge cell. Restyling Filament's table into that shape
 * means fighting every cell; rendering the row directly is less code and does
 * not break on upgrade.
 *
 * The resource is kept for the record view, so opening a job still goes through
 * ViewJob with its actions.
 */
class ListJobs extends ListRecords
{
    protected static string $resource = JobResource::class;

    protected string $view = 'filament.shop.resources.jobs.pages.list-jobs';

    /** Free text over reference, customer name and service name. */
    public string $q = '';

    /** '', or one of Job::STATUS_LABELS. */
    public string $status = '';

    /** '', 'today', 'week', 'overdue'. */
    public string $due = '';

    public function jobs(): LengthAwarePaginator
    {
        return $this->query()->paginate(25);
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        $shopId = auth()->user()?->currentShopId() ?? 0;

        return Job::query()
            ->where('shop_id', $shopId)
            ->with(['user', 'service', 'payment'])
            ->when($this->q !== '', function (Builder $query): void {
                $term = '%'.trim($this->q).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('reference', 'like', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term))
                        ->orWhereHas('service', fn (Builder $s) => $s->where('name', 'like', $term));
                });
            })
            // An unknown status shows nothing rather than everything: silently
            // ignoring a filter is how a screen lies about what it is showing.
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->due === 'today', fn (Builder $query) => $query
                ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()]))
            ->when($this->due === 'week', fn (Builder $query) => $query
                ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfWeek()]))
            ->when($this->due === 'overdue', fn (Builder $query) => $query
                ->where('due_at', '<', Carbon::now())
                ->whereNotIn('status', ['collected', 'cancelled']))
            ->orderByDesc('created_at');
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return ['' => 'All statuses'] + Job::STATUS_LABELS;
    }

    /** @return array<string, string> */
    public function dueOptions(): array
    {
        return [
            '' => 'Any time',
            'today' => 'Due today',
            'week' => 'This week',
            'overdue' => 'Overdue',
        ];
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = '';
        $this->due = '';
    }

    public function hasFilters(): bool
    {
        return $this->q !== '' || $this->status !== '' || $this->due !== '';
    }

    /** Maps a status onto the pill classes the shop theme already defines. */
    public function pillFor(string $status): string
    {
        return match ($status) {
            'quoted', 'awaiting_proof' => 'pending',
            'approved', 'in_production' => 'live',
            'ready' => 'done',
            'collected' => 'paid',
            'cancelled' => 'cancel',
            default => 'pending',
        };
    }
}
