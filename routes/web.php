<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
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
Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account');

// PayMongo calls this directly — no session, no CSRF token, signature instead.
Route::post('/webhooks/paymongo', \App\Http\Controllers\PayMongoWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.paymongo');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
