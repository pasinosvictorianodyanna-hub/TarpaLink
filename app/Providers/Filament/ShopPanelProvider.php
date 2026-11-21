<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The shop owner's panel: /shop.
 *
 * Separate panel, separate login page, same guard. The top bar carries the
 * shop's own name — an owner should never be unsure which shop they are editing.
 */
class ShopPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('shop')
            ->path('shop')
            ->login()
            ->brandName(fn (): string => auth()->user()?->currentShop()?->name ?? 'Shop panel')
            ->colors([
                'primary' => Color::hex('#0092E4'),
            ])
            ->discoverResources(in: app_path('Filament/Shop/Resources'), for: 'App\Filament\Shop\Resources')
            ->discoverPages(in: app_path('Filament/Shop/Pages'), for: 'App\Filament\Shop\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Shop/Widgets'), for: 'App\Filament\Shop\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Operations',
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
            ]);
    }
}
