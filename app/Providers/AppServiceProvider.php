<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\JobLine;
use App\Models\PageSection;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PriceOption;
use App\Models\PriceTier;
use App\Models\Proof;
use App\Models\QuantityDiscount;
use App\Models\Service;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\JobActivityPolicy;
use App\Policies\JobLinePolicy;
use App\Policies\JobPolicy;
use App\Policies\PageSectionPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PlanPolicy;
use App\Policies\PriceOptionPolicy;
use App\Policies\PriceTierPolicy;
use App\Policies\ProofPolicy;
use App\Policies\QuantityDiscountPolicy;
use App\Policies\ServicePolicy;
use App\Policies\ShopPolicy;
use App\Policies\SiteSettingPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->authorization();

        // A visible sign-out in the admin panel. Filament keeps its own behind
        // the avatar menu, which is a click on an unlabelled picture — and while
        // the panel theme was uncompiled there was nothing to click at all.
        //
        // The shop side no longer needs this hook: its sign-out is a real form in
        // the console's sidebar footer.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn () => view('filament.sign-out-button'),
        );

        // The footer blurb is CMS copy but appears on every page, so it is shared
        // with the layout rather than threaded through every controller.
        View::composer('layouts.app', function ($view): void {
            $footer = PageSection::where('key', 'footer')
                ->where('status', 'published')
                ->first()
                ?->content;

            $view->with('footerBlurb', $footer['blurb'] ?? null);
        });
    }

    /**
     * One policy per model, registered explicitly.
     *
     * There is deliberately no `Gate::before` administrator bypass. A blanket
     * "admin can do anything" makes rules like "nobody may edit the status trail"
     * or "an admin may not delete their own account" impossible to express —
     * the bypass wins before the policy is ever consulted. Every grant below is
     * therefore written out in the policy that owns it.
     *
     * Registration is explicit rather than relying on convention, so a reviewer
     * can see the whole authorization surface in one place.
     */
    private function authorization(): void
    {
        Gate::policy(Shop::class, ShopPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(PriceTier::class, PriceTierPolicy::class);
        Gate::policy(PriceOption::class, PriceOptionPolicy::class);
        Gate::policy(QuantityDiscount::class, QuantityDiscountPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(JobLine::class, JobLinePolicy::class);
        Gate::policy(JobActivity::class, JobActivityPolicy::class);
        Gate::policy(Proof::class, ProofPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PageSection::class, PageSectionPolicy::class);
        Gate::policy(SiteSetting::class, SiteSettingPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
