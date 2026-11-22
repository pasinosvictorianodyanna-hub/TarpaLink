<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a shop account waits.
 *
 * Between registering and being approved there is nothing to show an applicant:
 * no shop, no queue, no console. The honest answer is a page that says which
 * state their request is in — waiting, refused with a reason, or approved but
 * paused — rather than the 403 the console used to give them, or the empty
 * customer job list that signing in used to land on.
 *
 * It is also how an applicant learns they were approved, without any mail: sign
 * in and this page either explains the wait or sends them straight through.
 */
class WaitingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Approved, attached and trading: there is nothing to wait for.
        if ($user->worksInShop() && $user->currentShop()?->isActive()) {
            return redirect()->route('console.dashboard');
        }

        $application = ShopRequest::where('user_id', $user->id)
            ->latest('id')
            ->first();

        $shop = $user->worksInShop() ? $user->currentShop() : null;

        return view('console.waiting', [
            'user' => $user,
            'application' => $application,
            'shop' => $shop,
            'paused' => $shop !== null && ! $shop->isActive(),
            'signedInAs' => $user->email,
        ]);
    }
}
