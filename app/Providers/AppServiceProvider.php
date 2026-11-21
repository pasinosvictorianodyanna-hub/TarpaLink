<?php

namespace App\Providers;

use App\Support\ShopContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not singleton: a queue worker handles many requests' worth of
        // jobs in one process and must not carry one user's shop into the next.
        $this->app->scoped(ShopContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
