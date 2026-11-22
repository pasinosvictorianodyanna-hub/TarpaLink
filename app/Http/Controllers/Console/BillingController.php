<?php

namespace App\Http\Controllers\Console;

use App\Models\Plan;
use App\Services\Billing as BillingService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Plan, crossover math, and an upgrade path.
 *
 * The crossover numbers are computed, not hardcoded: a plan is worth it once the
 * commission it saves exceeds the fee it costs. If the rates ever change, these
 * figures move with them.
 */
class BillingController extends ConsoleController
{
    public function index(): View
    {
        $this->requireManager();

        $shop = $this->shop();
        $current = $shop->currentPlan();

        $plans = Plan::orderBy('sort')->get()->map(function (Plan $plan) use ($current): array {
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
                'note' => $note,
            ];
        })->all();

        return $this->page(
            'billing',
            [
                'title' => 'Billing',
                'sub' => 'Your plan, the commission on your next payout, and past invoices',
            ],
            'console.billing',
            [
                'current' => $current,
                'plans' => $plans,
                'invoices' => $shop->invoices()->take(10)->get(),
            ],
        );
    }

    public function choose(Request $request): RedirectResponse
    {
        $this->requireManager();

        $data = $request->validate(['code' => ['required', 'string']]);

        $shop = $this->shop();
        $plan = Plan::where('code', $data['code'])->first();

        // An unknown code is a stale page, not an error worth a stack trace.
        if ($plan === null) {
            return back()->withErrors(['code' => 'That plan no longer exists.']);
        }

        if ($shop->plan === $plan->code) {
            return back();
        }

        app(BillingService::class)->subscribe($shop, $plan);

        return back()->with(
            'status',
            "Switched to {$plan->name}. Commission on your next payout is now {$plan->commissionLabel()}; past payouts are unchanged.",
        );
    }
}
