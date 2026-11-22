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

        $user = User::where('email', $credentials['email'])->first();

        // Administrators must authenticate exclusively through /admin/login,
        // never through the customer/shop storefront sign-in page (/login).
        if ($user?->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => 'Administrator accounts cannot sign in here. Please use the admin login at /admin/login.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($request, $request->user()));
    }

    /**
     * Where to send someone who has just signed in.
     *
     * `intended` only wins when this user can actually reach it. Without that
     * check, a customer who once followed the footer's "Admin sign in" link —
     * which records `/admin` as the intended URL — is sent straight back to a
     * 403 the moment they sign in at the storefront. The login looks broken,
     * and nothing on the page says the problem was a URL they visited minutes
     * earlier as a guest.
     *
     * The path is rebuilt rather than reused so a full URL cannot smuggle in a
     * different host; `redirect()->to()` makes it absolute again.
     */
    private function destinationFor(Request $request, ?User $user): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $intended !== '') {
            $path = (string) (parse_url($intended, PHP_URL_PATH) ?: '/');
            $query = parse_url($intended, PHP_URL_QUERY);

            if ($this->mayReach($user, $path)) {
                return $query === null ? $path : $path.'?'.$query;
            }
        }

        return $this->homeFor($user);
    }

    /**
     * Whether this user belongs anywhere near the page they were bounced from.
     *
     * Only the three restricted areas are named here. Everything else is public
     * or covered by a policy that answers for itself — duplicating those rules
     * in a second place is how the two get to disagree.
     */
    private function mayReach(?User $user, string $path): bool
    {
        if ($user === null) {
            return false;
        }

        $area = explode('/', trim($path, '/'))[0];

        return match ($area) {
            'admin' => $user->isAdmin(),
            'shop', 'console' => $user->isShopOwner() && $user->currentShop()?->isActive() === true,
            default => true,
        };
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
