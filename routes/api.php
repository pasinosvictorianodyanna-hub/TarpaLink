<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Middleware\VerifyAdminApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrative API — for the CMS only
|--------------------------------------------------------------------------
|
| The CMS is the screen; this engine is where the work happens. Every route
| here is the same service the engine's own panel calls, so the two front ends
| cannot drift apart in behaviour — only in where the button sits.
|
| Bound by `:id` rather than by slug: a slug is the engine's public identity
| for a shop, and an administrative endpoint should not have to know it.
|
*/

Route::prefix('v1')
    ->middleware(VerifyAdminApiToken::class)
    ->group(function (): void {
        Route::get('/health', [AdminApiController::class, 'health'])->name('api.health');
        Route::get('/admin/summary', [AdminApiController::class, 'summary'])->name('api.admin.summary');

        Route::get('/admin/shops', [AdminApiController::class, 'shops'])->name('api.admin.shops');
        Route::post('/admin/shops/{shop:id}/approve', [AdminApiController::class, 'approveShop'])
            ->name('api.admin.shops.approve');
        Route::post('/admin/shops/{shop:id}/suspend', [AdminApiController::class, 'suspendShop'])
            ->name('api.admin.shops.suspend');

        Route::get('/admin/users', [AdminApiController::class, 'users'])->name('api.admin.users');

        Route::get('/admin/payouts', [AdminApiController::class, 'payouts'])->name('api.admin.payouts');
        Route::post('/admin/payouts/{payment:id}/release', [AdminApiController::class, 'releasePayout'])
            ->name('api.admin.payouts.release');
    });
