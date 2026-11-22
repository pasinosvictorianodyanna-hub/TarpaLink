<?php

namespace App\Filament\Shop\Pages;

use App\Models\Invoice;
use App\Models\Plan;
use App\Services\Billing as BillingService;
use App\Support\Money;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Plan, crossover math, and an upgrade path.
 *
 * The crossover numbers are computed, not hardcoded: a plan is worth it once the
 * commission it saves exceeds the fee it costs. If the rates ever change, these
 * figures move with them.
 */
class Billing extends Page
{
    protected string $view = 'filament.shop.pages.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Plan & billing';

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Plan & billing';

    public static function canView(): bool
    {
        return auth()->user()?->canManagePrices() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canView(), 403);
    }

    public function currentPlan(): ?Plan
    {
        return auth()->user()?->currentShop()?->currentPlan();
    }

    /**
     * @return array<int, array{plan: Plan, current: bool, crossover: ?int, note: string}>
     */
    public function plans(): array
    {
        $current = $this->currentPlan();

        return Plan::orderBy('sort')->get()->map(function (Plan $plan) use ($current): array {
            $crossover = $plan->crossoverFrom($plan->lowerPlan());

            $note = match (true) {
                $crossover === null => $plan->isFree()
                    ? 'No monthly fee.'
                    : 'Same rate as the plan below — never worth upgrading to.',
                $crossover === 0 => 'Cheaper on both counts.',
                default => 'Worth it above '.Money::short($crossover).' of jobs a month.',
            };

            return [
                'plan' => $plan,
                'current' => $current?->code === $plan->code,
                'crossover' => $crossover,
                'note' => $note,
            ];
        })->all();
    }

    /** @return \Illuminate\Support\Collection<int, Invoice> */
    public function invoices()
    {
        return auth()->user()?->currentShop()?->invoices()->take(10)->get() ?? collect();
    }

    public function choose(string $code): void
    {
        $shop = auth()->user()?->currentShop();
        $plan = Plan::where('code', $code)->first();

        if (! $shop || ! $plan) {
            Notification::make()->danger()->title('Could not change plan')->send();

            return;
        }

        if ($shop->plan === $plan->code) {
            return;
        }

        app(BillingService::class)->subscribe($shop, $plan);

        Notification::make()
            ->success()
            ->title("Switched to {$plan->name}")
            ->body("Commission on your next payout is now {$plan->commissionLabel()}. Past payouts are unchanged.")
            ->send();
    }
}
