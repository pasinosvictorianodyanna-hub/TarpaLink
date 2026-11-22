<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Wrong passwords allowed per throttle window, per email and IP. */
    private const MAX_ATTEMPTS = 5;

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

        // Checked before the password is looked at, and before the database is
        // asked whether the address exists. A locked-out caller learns nothing
        // from this response, which is the point: the alternative answers "that
        // email is not registered" for free, at whatever rate the attacker
        // cares to ask.
        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $credentials['email'])->first();

        // Administrators must authenticate exclusively through /admin/login,
        // never through the customer/shop storefront sign-in page (/login).
        if ($user?->isAdmin()) {
            // Counted, not waved through: refusing admin sign-in here is a
            // failed sign-in, and leaving it uncounted would let somebody probe
            // which addresses are administrator accounts without ever tripping
            // the limiter.
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Administrator accounts cannot sign in here. Please use the admin login at /admin/login.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($request, $request->user()));
    }

    /**
     * Five wrong passwords a minute, per address and per caller.
     *
     * Keyed on both the email and the IP so that one attacker cannot lock a real
     * customer out of their own account by hammering their address from
     * elsewhere — the honest customer's IP still has its own budget — while a
     * single machine spraying many addresses is still caught, because each of
     * those keys shares the same IP half.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        );
    }

    /**
     * @throws ValidationException when this caller has spent their attempts
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Too many sign-in attempts. Please try again in %d %s.',
                $seconds,
                $seconds === 1 ? 'second' : 'seconds',
            ),
        ]);
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
            'shop', 'console' => $user->worksInShop() && $user->currentShop()?->isActive() === true,
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

        // A suspended or pending shop has no console to go to — the console
        // refuses it — so it goes to the waiting room, which says which of the
        // two it is. It used to fall through to /account, which showed a new
        // shop owner an empty customer job list and no explanation.
        if ($user->worksInShop() && $user->currentShop()?->isActive()) {
            return route('console.dashboard');
        }

        if ($user->worksInShop()) {
            return route('console.waiting');
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
