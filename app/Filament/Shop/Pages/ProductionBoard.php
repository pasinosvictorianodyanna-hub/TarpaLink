<?php

namespace App\Filament\Shop\Pages;

use App\Models\Job;
use App\Services\JobWorkflow;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Kanban of the shop's active jobs.
 *
 * Dropping a card calls the same JobWorkflow the jobs table uses, so an illegal
 * drop is refused here exactly as it is refused there — the UI is never the only
 * thing standing between a shop and a corrupt status.
 */
class ProductionBoard extends Page
{
    protected string $view = 'filament.shop.pages.production-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Production board';

    protected static string|UnitEnum|null $navigationGroup = 'Work';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Production board';

    public static function getNavigationBadge(): ?string
    {
        $count = Job::where('shop_id', auth()->user()?->currentShopId() ?? 0)
            ->whereIn('status', JobWorkflow::BOARD)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /** @return array<int, array{key: string, label: string, jobs: Collection}> */
    public function columns(): array
    {
        $jobs = Job::with(['user', 'service'])
            ->where('shop_id', auth()->user()?->currentShopId() ?? 0)
            ->whereIn('status', JobWorkflow::BOARD)
            ->orderBy('due_at')
            ->get()
            ->groupBy('status');

        return collect(JobWorkflow::BOARD)
            ->map(fn (string $status): array => [
                'key' => $status,
                'label' => Job::STATUS_LABELS[$status] ?? $status,
                'jobs' => $jobs->get($status, collect()),
            ])
            ->all();
    }

    public function moveJob(int $jobId, string $status): void
    {
        $job = Job::where('shop_id', auth()->user()?->currentShopId() ?? 0)->find($jobId);

        if (! $job) {
            Notification::make()->danger()->title('Job not found in this shop')->send();

            return;
        }

        try {
            JobWorkflow::transition($job, $status, auth()->user());
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('That move is not allowed')
                ->body(collect($e->errors())->flatten()->first())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("{$job->reference} moved to ".Job::STATUS_LABELS[$status])
            ->send();
    }
}
