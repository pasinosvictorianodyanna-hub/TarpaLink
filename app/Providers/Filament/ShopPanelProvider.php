<?php

namespace App\Providers\Filament;

use App\Http\Controllers\PayoutExportController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ShopPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('shop')
            ->path('shop')
            ->login()
            ->brandName(fn (): string => auth()->user()?->currentShop()?->name ?? 'TarpaLink')
            ->colors([
                'primary' => Color::Stone,
            ])
            // The design is paper stock and ink: there is no dark version of it.
            // Without this, Filament follows the operating system and a shop
            // owner on a dark-mode machine sees a black panel that shares no
            // visual language with the storefront their customers use.
            ->darkMode(false)
            // Shared with the admin panel: one design system, one palette.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.partials.design-tokens')->render(),
            )
            ->discoverResources(in: app_path('Filament/Shop/Resources'), for: 'App\Filament\Shop\Resources')
            ->discoverPages(in: app_path('Filament/Shop/Pages'), for: 'App\Filament\Shop\Pages')
            // No `->pages([Dashboard::class])`: the dashboard is discovered from
            // App\Filament\Shop\Pages\Dashboard, which extends the stock page but
            // adds the shop's own subheading. Registering both would put two
            // pages on `/shop` and let registration order decide which wins.
            ->discoverWidgets(in: app_path('Filament/Shop/Widgets'), for: 'App\Filament\Shop\Widgets')
            // No AccountWidget: it renders a "Welcome, Kevin Cruz" card across
            // the top of the dashboard, and the same identity is already in the
            // topbar user menu. On a screen made of figures it is just noise.
            ->widgets([])
            // Work before money: the order the shop's day actually runs in.
            ->navigationGroups([
                'Work',
                'Catalogue',
                'Money',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Panel-relative, so this lives at /shop/reports/export and inherits
            // the panel's session, auth and `canAccessPanel()` checks.
            ->authenticatedRoutes(function (): void {
                Route::get('/reports/export', PayoutExportController::class)
                    ->name('reports.export');
            });
    }
}
