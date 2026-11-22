<?php

namespace App\Http\Controllers;

use App\Models\ShopRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The public form a shop fills in to ask for an account.
 *
 * It creates the **account and the request together**, and never a shop. The
 * applicant picks their own password here, which is what makes the rest of the
 * flow independent of mail delivery: approval only has to attach a shop to an
 * account that already exists, so there is no set-password link to lose.
 *
 * The account is created as `shop_owner` but that grants nothing on its own —
 * `ConsoleController::shop()` admits only a user attached to an *active* shop,
 * and this one has none until an administrator approves it. The role is a
 * label; the shop attachment is the permission.
 *
 * The rate limit is on the route rather than here: this is an unauthenticated
 * write, so the guard belongs where every other unauthenticated write puts it.
 */
class ShopRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $applicant = $request->user();

        // `role` holds one value: approving this would demote an administrator
        // out of the panel, so the door is shut before a request exists.
        if ($applicant?->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => 'Administrator accounts cannot apply for a shop.',
            ]);
        }

        // A bot filling every field fills the hidden one too. Rejecting silently
        // — with the same response a human gets — avoids teaching it which field
        // was the trap. Same redirect, so the two are indistinguishable.
        if (filled($request->input('website'))) {
            return $this->received();
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                // A signed-in applicant keeps their own address; a guest must
                // not take one that already belongs to somebody else.
                Rule::unique('users', 'email')->ignore($applicant?->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'services' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Only a guest is choosing a password; a signed-in applicant
            // already has one and is not asked to retype it.
            'password' => [$applicant === null ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // One open application per account. Two rows for one shop is how a
        // shop ends up split across two accounts it does not know it has.
        if ($applicant !== null && ShopRequest::where('user_id', $applicant->id)->waiting()->exists()) {
            return $this->received()
                ->withErrors(['business_name' => 'You already have a request waiting. We will review it first.']);
        }

        $user = DB::transaction(function () use ($applicant, $data): User {
            if ($applicant !== null) {
                // A customer applying for a shop becomes a shop account here.
                // The role is a label until a shop is attached, so this changes
                // nothing they can reach — the console still needs an active shop.
                if (! $applicant->isShopOwner()) {
                    $applicant->forceFill(['role' => 'shop_owner'])->save();
                }

                $user = $applicant;
            } else {
                $user = User::create([
                    'name' => $data['contact_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'city' => $data['city'],
                    // The User model's `hashed` cast handles this.
                    'password' => $data['password'],
                    'role' => 'shop_owner',
                ]);
            }

            ShopRequest::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'contact_name' => $data['contact_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'],
                'address' => $data['address'],
                'services' => $data['services'] ?? null,
                'message' => $data['message'] ?? null,
                'status' => 'new',
            ]);

            return $user;
        });

        if ($applicant === null) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return $this->received();
    }

    /**
     * Where every accepted submission goes: the waiting room.
     *
     * Not a flash on the form — the applicant is signed in now, and the room
     * stays truthful about the decision afterwards, which a one-shot banner
     * cannot be.
     */
    private function received(): RedirectResponse
    {
        return redirect()->route('console.waiting');
    }
}
