<?php

namespace App\Http\Controllers\Console;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switching which shop the console is looking at.
 *
 * The panel had a picker for this; the console did not, which meant an owner of
 * two shops had to go back to the panel to change shops — the one link that
 * would still have changed the shell under them.
 *
 * The session is a preference, never a permission. A posted shop id is user
 * input like any other, so it is checked against the user's own shops and
 * against the shop still trading before it is stored.
 */
class ShopSwitchController extends ConsoleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['shop_id' => ['required', 'integer']]);

        $target = $this->user()->shops()
            ->whereKey($data['shop_id'])
            ->where('shops.status', 'active')
            ->first();

        if ($target === null) {
            return back()->withErrors(['shop_id' => 'You cannot switch to that shop — it is either not yours or not trading.']);
        }

        session()->put('shop_id', $target->id);

        // Back to where the switch was made, so the owner stays on the screen
        // they were reading rather than being thrown at the dashboard.
        return redirect()->to($this->safeReturnTo($request));
    }

    /**
     * `return_to` round-trips through the browser, so it is user input by the
     * time it comes back. Only a path on this host is honoured: an absolute URL
     * here would be an open redirect.
     */
    private function safeReturnTo(Request $request): string
    {
        $to = (string) $request->input('return_to', '');

        if ($to === '' || str_starts_with($to, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $to)) {
            return route('console.dashboard');
        }

        if (! str_starts_with($to, '/') || ! str_starts_with($to, '/console')) {
            return route('console.dashboard');
        }

        return $to;
    }
}
