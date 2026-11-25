<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Console\BillingController as ConsoleBillingController;
use App\Http\Controllers\Console\BoardController as ConsoleBoardController;
use App\Http\Controllers\Console\DashboardController as ConsoleDashboardController;
use App\Http\Controllers\Console\JobController as ConsoleJobController;
use App\Http\Controllers\Console\PriceMatrixController as ConsolePriceMatrixController;
use App\Http\Controllers\Console\QuantityDiscountController as ConsoleDiscountController;
use App\Http\Controllers\Console\ReportsController as ConsoleReportsController;
use App\Http\Controllers\Console\ServiceController as ConsoleServiceController;
use App\Http\Controllers\Console\ShopSwitchController as ConsoleShopSwitchController;
use App\Http\Controllers\Console\WaitingController as ConsoleWaitingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboundMailController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\PayoutExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProofController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopRequestController;
use App\Http\Middleware\EnsureShopIsOpen;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
Route::get('/shops/{shop}', [ShopController::class, 'show'])->name('shops.show');
Route::get('/shops/{shop}/build', [ShopController::class, 'builder'])->name('shops.builder');

Route::post('/api/quote', QuoteController::class)->name('quote');

// A shop asking to be let in. Public and unauthenticated on purpose: the whole
// point is that a shop with no account yet can apply. It creates a request, not
// a user and not a shop — an administrator decides, and the account is created
// at that point (see ShopOnboarding).
Route::post('/open-a-shop', [ShopRequestController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('shop-request.store');

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
// The design file the customer sends the shop. Written by the customer, read by
// the shop — the opposite direction to a proof, and gated the same way.
Route::post('/jobs/{job}/artwork', [ArtworkController::class, 'store'])
    ->middleware('auth')->name('jobs.artwork.store');
Route::get('/jobs/{job}/artwork/{artwork}/file', [ArtworkController::class, 'file'])
    ->middleware('auth')->name('jobs.artwork.file');
// Cancelling before the proof is approved refunds in full. The controller checks
// ownership; a shop may view a job it is printing but may not cancel it here.
Route::post('/jobs/{job}/cancel', [JobController::class, 'cancel'])
    ->middleware('auth')->name('jobs.cancel');
Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account');
Route::redirect('/account/jobs', '/account')->middleware('auth');

// The shop console — the whole shop surface, not one screen of it.
//
// It used to be a single route because Filament still owned the other seven
// screens, so every link out of the dashboard changed the shell. One group now,
// one layout, one place where the nav is decided.
// The waiting room sits under the same prefix but outside the shop gate — an
// applicant with no active shop is exactly who it is for.
Route::middleware('auth')->prefix('console')->name('console.')->group(function (): void {
    Route::get('/waiting', ConsoleWaitingController::class)->name('waiting');
});

Route::middleware(['auth', EnsureShopIsOpen::class])->prefix('console')->name('console.')->group(function (): void {
    Route::get('/', ConsoleDashboardController::class)->name('dashboard');

    // Which shop the console is looking at. A session preference, checked
    // against the user's own shops every time it is set.
    Route::post('/shop', ConsoleShopSwitchController::class)->name('shop');

    Route::get('/jobs', [ConsoleJobController::class, 'index'])->name('jobs');
    Route::get('/jobs/{job}', [ConsoleJobController::class, 'show'])->name('jobs.show');
    Route::post('/jobs/{job}/status', [ConsoleJobController::class, 'advance'])->name('jobs.status');
    Route::post('/jobs/{job}/proof', [ConsoleJobController::class, 'sendProof'])->name('jobs.proof');
    // The chase that used to live only in the Filament panel's Needs attention
    // widget. Retiring the panel would otherwise have quietly removed it.
    Route::post('/jobs/{job}/nudge', [ConsoleJobController::class, 'nudge'])->name('jobs.nudge');

    Route::get('/production-board', [ConsoleBoardController::class, 'index'])->name('board');
    Route::post('/production-board/{job}', [ConsoleBoardController::class, 'move'])->name('board.move');

    Route::get('/price-matrix', [ConsolePriceMatrixController::class, 'index'])->name('prices');

    Route::get('/reports', ConsoleReportsController::class)->name('reports');
    // Declared before the page so `/reports/export` is never read as a report id.
    Route::get('/reports/export', PayoutExportController::class)->name('reports.export');

    Route::get('/billing', [ConsoleBillingController::class, 'index'])->name('billing');
    Route::post('/billing', [ConsoleBillingController::class, 'choose'])->name('billing.choose');

    Route::get('/services', [ConsoleServiceController::class, 'index'])->name('services');
    Route::get('/services/create', [ConsoleServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [ConsoleServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}/edit', [ConsoleServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [ConsoleServiceController::class, 'update'])->name('services.update');

    Route::get('/quantity-discounts', [ConsoleDiscountController::class, 'index'])->name('discounts');
    Route::get('/quantity-discounts/create', [ConsoleDiscountController::class, 'create'])->name('discounts.create');
    Route::post('/quantity-discounts', [ConsoleDiscountController::class, 'store'])->name('discounts.store');
    Route::get('/quantity-discounts/{discount}/edit', [ConsoleDiscountController::class, 'edit'])->name('discounts.edit');
    Route::put('/quantity-discounts/{discount}', [ConsoleDiscountController::class, 'update'])->name('discounts.update');
    Route::delete('/quantity-discounts/{discount}', [ConsoleDiscountController::class, 'destroy'])->name('discounts.destroy');
});

Route::get('/account/profile', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');

// The two profile writes are JSON: the form is a Vue island and needs field-level
// errors, not a full page reload that throws away the other half-filled card.
Route::patch('/api/profile', [ProfileController::class, 'update'])->middleware('auth')->name('api.profile.update');
Route::put('/api/profile/password', [ProfileController::class, 'password'])->middleware('auth')->name('api.profile.password');

// PayMongo calls this directly — no session, no CSRF token, signature instead.
// CSRF is waived for `webhooks/*` in bootstrap/app.php, not here: a per-route
// `withoutMiddleware` naming the wrong class fails silently.
Route::post('/webhooks/paymongo', PayMongoWebhookController::class)
    ->name('webhooks.paymongo');

// The other direction: a customer's reply comes back in, signed the same way.
// Both webhooks are unauthenticated by design — the signature is the credential,
// and a session cookie is not something a provider can hold.
Route::post('/webhooks/inbound', InboundMailController::class)
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
