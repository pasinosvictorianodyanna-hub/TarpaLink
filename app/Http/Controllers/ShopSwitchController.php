<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Move the shop panel from one shop to another.
 *
 * The choice lives in the session rather than the URL. A URL that carries the
 * shop would have to be threaded through every Filament link, and the first one
 * somebody forgot would silently drop the user back into the wrong shop's
 * figures — which is the kind of bug that shows up as a wrong payout, not as an
 * error.
 *
 * Membership is re-checked here. A posted shop id is user input like any other,
 * and `currentShop()` trusting it unchecked would let an owner read a stranger's
 * jobs by editing one form field.
 */
class ShopSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        // One query answers both halves of the question: is this shop theirs,
        // and is it trading? Answering only the first would let the form report
        // success for a switch that `currentShop()` then quietly ignores.
        $target = $user?->shops()
            ->whereKey((int) $data['shop_id'])
            ->where('shops.status', 'active')
            ->first();

        abort_unless($target !== null, 403);

        $request->session()->put('shop_id', $target->id);

        return back();
    }
}
