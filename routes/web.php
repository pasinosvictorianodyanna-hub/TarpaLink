<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProofController;
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
// Proof files live on the private disk; these routes are the only way in, and
// each one checks the job before serving or changing anything.
Route::get('/jobs/{job}/proofs/{proof}/file', [ProofController::class, 'file'])
    ->middleware('auth')->name('jobs.proof.file');
Route::post('/jobs/{job}/proofs/{proof}/approve', [ProofController::class, 'approve'])
    ->middleware('auth')->name('jobs.proof.approve');
Route::post('/jobs/{job}/proofs/{proof}/reject', [ProofController::class, 'reject'])
    ->middleware('auth')->name('jobs.proof.reject');
Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account');
Route::redirect('/account/jobs', '/account')->middleware('auth');
Route::redirect('/shop/plan-and-billing', '/shop/billing')->middleware('auth');

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
// CSRF is waived for `webhooks/*` in bootstrap/app.php, not here: a per-route
// `withoutMiddleware` naming the wrong class fails silently.
Route::post('/webhooks/paymongo', \App\Http\Controllers\PayMongoWebhookController::class)
    ->name('webhooks.paymongo');

// The other direction: a customer's reply comes back in, signed the same way.
// Both webhooks are unauthenticated by design — the signature is the credential,
// and a session cookie is not something a provider can hold.
Route::post('/webhooks/inbound', \App\Http\Controllers\InboundMailController::class)
    ->name('webhooks.inbound');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);

// Password recovery. Throttled at the route as well as by the broker: the broker
// throttles per account, which does nothing about somebody working through a
// list of addresses.
Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])
    ->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
    ->middleware(['guest', 'throttle:6,1'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
    ->middleware('guest')->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware(['guest', 'throttle:6,1'])->name('password.store');
// Registration is a customer door only. Whoever is already signed in is sent
// back to their own screen by the controller; shop and admin accounts are
// created inside the panels, never from this form.
Route::get('/register', [RegisteredUserController::class, 'show'])->name('register');
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Footer pages. Declared last and constrained to a known list, so it can never
// shadow a real route — a bare `/{slug}` would swallow everything.
Route::get('/{slug}', PageController::class)
    ->whereIn('slug', PageController::SLUGS)
    ->name('page');
