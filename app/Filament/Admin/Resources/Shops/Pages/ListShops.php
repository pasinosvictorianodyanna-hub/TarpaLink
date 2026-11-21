<?php

namespace App\Filament\Admin\Resources\Shops\Pages;

use App\Filament\Admin\Resources\Shops\ShopResource;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopApproval;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The shops screen from the prototype.
 *
 * Filament's table page is replaced by a view for the same reason as the shop
 * panel's jobs screen: the design's row carries an owner and two counts that
 * the table has to assemble anyway, and status is a pill.
 *
 * Only **approve** is offered inline, because it is the one action in the
 * queue that needs no explanation. Reject and suspend require a reason the shop
 * will read, so they stay on the record page where there is room to write it —
 * a destructive action with no room for context is how someone gets suspended
 * with "n/a" as the reason.
 */
class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    protected string $view = 'filament.admin.resources.shops.pages.list-shops';

    public string $q = '';

    /** '', 'pending', 'active', 'suspended'. */
    public string $status = '';

    public string $city = '';

    public function mount(): void
    {
        // The queue first: an admin who opens this page is usually here to approve.
        $this->status = 'pending';
    }

    public function shops(): LengthAwarePaginator
    {
        return $this->query()->paginate(25);
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        return Shop::query()
            ->withCount(['jobs', 'services'])
            ->with(['staff' => fn ($query) => $query->wherePivot('role', 'owner')])
            ->when($this->q !== '', function (Builder $query): void {
                $term = '%'.trim($this->q).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhereHas('staff', fn (Builder $s) => $s->where('name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->city !== '', fn (Builder $query) => $query->where('city', $this->city))
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")
            ->orderByDesc('created_at');
    }

    /** @return array<int, string> */
    public function cityOptions(): array
    {
        return Shop::query()->distinct()->orderBy('city')->pluck('city', 'city')->all();
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return [
            '' => 'All statuses',
            'pending' => 'Pending',
            'active' => 'Active',
            'suspended' => 'Suspended',
        ];
    }

    public function ownerOf(Shop $shop): ?User
    {
        return $shop->staff->first();
    }

    public function pillFor(string $status): string
    {
        return match ($status) {
            'active' => 'paid',
            'pending' => 'pending',
            'suspended' => 'cancel',
            default => 'pending',
        };
    }

    public function approve(int $shopId): void
    {
        $shop = Shop::findOrFail($shopId);

        try {
            app(ShopApproval::class)->approve($shop, auth()->user());
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Could not approve '.$shop->name)
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($shop->name.' approved')
            ->body('The owner can sign in to the shop panel now.')
            ->success()
            ->send();
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = '';
        $this->city = '';
    }

    public function hasFilters(): bool
    {
        return $this->q !== '' || $this->status !== '' || $this->city !== '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportShops()),
        ];
    }

    public function exportShops(): StreamedResponse
    {
        $shops = Shop::query()
            ->withCount(['jobs', 'services'])
            ->with(['staff' => fn ($query) => $query->wherePivot('role', 'owner')])
            ->orderBy('name')
            ->cursor();

        return response()->streamDownload(function () use ($shops): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Shop', 'Owner', 'City', 'Services', 'Jobs', 'Plan', 'Status', 'Joined']);

            foreach ($shops as $shop) {
                fputcsv($out, [
                    $shop->name,
                    $shop->staff->first()?->name ?? '',
                    $shop->city,
                    $shop->services_count,
                    $shop->jobs_count,
                    $shop->planDefinition?->name ?? $shop->plan,
                    $shop->status,
                    $shop->created_at?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($out);
        }, 'tarpalink-shops-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
