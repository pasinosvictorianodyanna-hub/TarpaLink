<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
Route::get('/shops/{shop}', [ShopController::class, 'show'])->name('shops.show');
Route::get('/shops/{shop}/build', [ShopController::class, 'builder'])->name('shops.builder');

Route::post('/api/quote', QuoteController::class)->name('quote');

Route::get('/shops/{shop}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/shops/{shop}/checkout', [CheckoutController::class, 'store'])
    ->middleware('auth')
    ->name('checkout.store');

Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/jobs/{job}/receipt', [JobController::class, 'receipt'])->name('jobs.receipt');
Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account');

// The shop console. Filament keeps the screens that are not ported yet, so this
// is one route for now rather than a group with its own prefix.
Route::get('/console', [ConsoleController::class, 'dashboard'])
    ->middleware('auth')
    ->name('console.dashboard');

Route::get('/account/profile', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');

// The two profile writes are JSON: the form is a Vue island and needs field-level
// errors, not a full page reload that throws away the other half-filled card.
Route::patch('/api/profile', [ProfileController::class, 'update'])->middleware('auth')->name('api.profile.update');
Route::put('/api/profile/password', [ProfileController::class, 'password'])->middleware('auth')->name('api.profile.password');

// PayMongo calls this directly — no session, no CSRF token, signature instead.
Route::post('/webhooks/paymongo', \App\Http\Controllers\PayMongoWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.paymongo');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Footer pages. Declared last and constrained to a known list, so it can never
// shadow a real route — a bare `/{slug}` would swallow everything.
Route::get('/{slug}', PageController::class)
    ->whereIn('slug', PageController::SLUGS)
    ->name('page');
