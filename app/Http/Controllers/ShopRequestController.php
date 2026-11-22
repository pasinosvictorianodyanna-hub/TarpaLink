<?php

namespace App\Http\Controllers;

use App\Models\ShopRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The public form a shop fills in to ask for an account.
 *
 * It creates a request, never an account and never a shop. Admission is a
 * decision an administrator makes, and the applicant has no way to influence it
 * beyond describing their business — which is why nothing here writes to
 * `users` or `shops`.
 *
 * The rate limit is on the route rather than here: this is an unauthenticated
 * write, so the guard belongs where every other unauthenticated write puts it.
 */
class ShopRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // A bot filling every field fills the hidden one too. Rejecting silently
        // — with the same message a human gets — avoids teaching it which field
        // was the trap.
        if (filled($request->input('website'))) {
            return $this->received($request);
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'services' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        ShopRequest::create($data + ['status' => 'new']);

        return $this->received($request, $data['email']);
    }

    private function received(Request $request, ?string $email = null): RedirectResponse
    {
        // A key of its own rather than `status`: the app's global banner sits at
        // the top of the page, and the confirmation belongs beside the form the
        // shop just filled in, not somewhere they have to scroll back to find.
        return redirect()
            ->route('page', 'open-a-shop')
            ->with('shop_request_sent', $email ?? true);
    }
}
