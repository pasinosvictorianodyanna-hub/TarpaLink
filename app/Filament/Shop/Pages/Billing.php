<?php

namespace App\Filament\Shop\Pages;

use App\Models\Plan;
use App\Services\Billing as BillingService;
use App\Support\Money;
use App\Support\ShopContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Current plan, the crossover maths, an upgrade path, and invoice history.
 * See docs/ISSUES.md #12.
 */
class Billing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $navigationLabel = 'Billing';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Billing';

    protected string $view = 'filament.shop.pages.billing';

    /** @var array<int, array<string, mixed>> */
    public array $planOptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $invoices = [];

    public string $currentPlan = '';

    public string $currentFee = '';

    public string $currentRate = '';

    public string $currentTagline = '';

    public function mount(): void
    {
        abort_if(app(ShopContext::class)->shop() === null, 403);

        $this->load();
    }

    public function upgrade(string $key): void
    {
        $shop = app(ShopContext::class)->shop();

        abort_if($shop === null, 403);

        $plan = Plan::where('key', $key)->firstOrFail();

        if ($plan->key === $shop->plan) {
            return;
        }

        app(BillingService::class)->upgrade($shop, $plan);

        Notification::make()
            ->title('You are now on '.$plan->name)
            ->body('The new '.$plan->commissionLabel().' rate applies from the next payout. Past payouts are unchanged.')
            ->success()
            ->send();

        $this->load();
    }

    private function load(): void
    {
        $shop = app(ShopContext::class)->shop();
        $billing = app(BillingService::class);

        $current = $billing->planFor($shop);
        $plans = $billing->plans();

        $this->currentPlan = $current?->name ?? ucfirst($shop->plan);
        $this->currentFee = $current?->feeLabel() ?? '—';
        $this->currentRate = $shop->commissionLabel();
        $this->currentTagline = $current?->tagline ?? '';

        $this->planOptions = $plans->map(function (Plan $plan) use ($current, $shop) {
            $crossover = $current && $plan->key !== $current->key
                ? $plan->crossoverAgainst($current)
                : null;

            return [
                'key' => $plan->key,
                'name' => $plan->name,
                'tagline' => $plan->tagline,
                'fee' => $plan->feeLabel(),
                'rate' => $plan->commissionLabel(),
                'is_current' => $plan->key === $shop->plan,
                'crossover' => $crossover === null ? null : Money::format($crossover),
                'is_cheaper' => $crossover === 0,
            ];
        })->all();

        $this->invoices = $shop->invoices()
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn ($invoice) => [
                'number' => $invoice->number,
                'amount' => Money::format($invoice->amount),
                'status' => $invoice->statusLabel(),
                'period' => $invoice->period_start?->format('M Y'),
                'issued' => $invoice->issued_at?->format('d M Y'),
                'paid' => $invoice->paid_at?->format('d M Y') ?? '—',
            ])
            ->all();
    }
}
