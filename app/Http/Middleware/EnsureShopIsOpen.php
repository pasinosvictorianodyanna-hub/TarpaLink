<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The console door.
 *
 * `ConsoleController::shop()` already refuses a request that has no active shop,
 * but it refuses it with a 403 — a dead end that tells a newly approved (or not
 * yet approved) owner nothing. This runs earlier and sends them somewhere that
 * explains the state they are in.
 *
 * The rule itself is unchanged and deliberately not re-invented here: work in a
 * shop, be attached to one, and have that shop trading. `role` alone has never
 * been enough — an account can be `shop_owner` with no shop at all, which is
 * exactly what an applicant is between registering and being approved.
 */
class EnsureShopIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->worksInShop()) {
            abort(403, 'The shop console is for a shop account.');
        }

        if ($user->currentShop()?->isActive() !== true) {
            return redirect()->route('console.waiting');
        }

        return $next($request);
    }
}
