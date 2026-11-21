<?php

namespace App\Providers;

use App\Models\PageSection;
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
}
