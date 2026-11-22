<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user()));
    }

    /**
     * Where a signed-in user belongs.
     *
     * Every role used to land on /account, which is the customer's job list. A
     * shop owner signing in saw an empty "My jobs" and an admin saw the same —
     * the screen they signed in for was never reached, and nothing on the page
     * said why.
     *
     * `intended` still wins: someone bounced off a protected URL goes back there
     * rather than to their home screen. This is only the fallback.
     */
    private function homeFor(?User $user): string
    {
        if ($user === null) {
            return route('account');
        }

        if ($user->isAdmin()) {
            return route('filament.admin.pages.dashboard');
        }

        // A suspended or pending shop has no console to go to — `canAccessPanel`
        // and the console both refuse it — so it falls through to the storefront
        // rather than landing on a 403 immediately after signing in.
        if ($user->isShopOwner() && $user->currentShop()?->isActive()) {
            return route('console.dashboard');
        }

        return route('account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
