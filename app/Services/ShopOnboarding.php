<?php

namespace App\Services;

use App\Mail\ShopInvited;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopRequest;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turning an approved request into a shop with somebody who can run it.
 *
 * This is the only place a shop and its first owner are created from a request,
 * and it is deliberately one transaction: a shop with no owner is invisible to
 * the console and unmanageable from the panel, and an owner with no shop is a
 * `shop_owner` who cannot open anything. Either both exist or neither does.
 *
 * The account is created with a random password that nobody is ever told. The
 * owner sets their own through the password-reset link, which is the same flow
 * a forgotten password uses — so there is no plaintext credential in the
 * database, in an email, or on an administrator's screen.
 */
final class ShopOnboarding
{
    /**
     * Approve a request: create the account and the shop, and send the invite.
     *
     * @return array{shop: Shop, user: User, link: string, sent: bool}
     */
    public function approve(ShopRequest $request, ?User $by = null): array
    {
        if (! $request->isNew()) {
            throw ValidationException::withMessages([
                'request' => 'This request has already been '.$request->status.'.',
            ]);
        }

        return DB::transaction(function () use ($request, $by): array {
            $user = $this->owner($request);
            $shop = $this->shop($request, $by);

            $user->shops()->syncWithoutDetaching([$shop->id => ['role' => 'owner']]);

            $request->forceFill([
                'status' => 'approved',
                'reason' => null,
                'shop_id' => $shop->id,
                'handled_by' => $by?->id,
                'handled_at' => now(),
            ])->save();

            // A reset token, not a password. It is written here so the link can
            // be handed over even when mail is not configured on the deployment.
            $link = $this->setPasswordLink($user);
            $sent = Notifier::send($user->email, new ShopInvited($shop, $user, $link));

            return ['shop' => $shop, 'user' => $user, 'link' => $link, 'sent' => $sent];
        });
    }

    /**
     * Re-send the invitation for a request that was already approved.
     *
     * Approval mints one token and flashes the link; if the administrator
     * closes that page, or the hour passes, the link is unrecoverable — the
     * token is stored hashed, so nothing can rebuild it. This mints a fresh one
     * from the same broker, which also means the previous link stops working:
     * there is never more than one way into the account.
     *
     * Nothing is created here. The account and the shop already exist; this
     * only reopens the door to them.
     *
     * @return array{shop: Shop, user: User, link: string, sent: bool}
     */
    public function reinvite(ShopRequest $request): array
    {
        if ($request->status !== 'approved' || $request->shop_id === null) {
            throw ValidationException::withMessages([
                'request' => 'Only an approved request with a live shop can be re-invited.',
            ]);
        }

        $shop = Shop::find($request->shop_id);
        $user = User::where('email', $request->email)->first();

        // The shop is `nullOnDelete`, so a deleted shop leaves an approved
        // request pointing at nothing. Say so rather than mailing a link to a
        // console that would 404 on arrival.
        if ($shop === null || $user === null) {
            throw ValidationException::withMessages([
                'request' => 'The account or the shop behind this request no longer exists.',
            ]);
        }

        $link = $this->setPasswordLink($user);
        $sent = Notifier::send($user->email, new ShopInvited($shop, $user, $link));

        return ['shop' => $shop, 'user' => $user, 'link' => $link, 'sent' => $sent];
    }

    /**
     * The applicant's account.
     *
     * Most applicants have never signed up, so this creates one. When the email
     * already belongs to an account — somebody who ordered as a customer first,
     * which the page copy invites — that account is reused and promoted rather
     * than duplicated: two rows for one person is how a shop ends up unable to
     * see its own history.
     */
    private function owner(ShopRequest $request): User
    {
        $existing = User::where('email', $request->email)->first();

        if ($existing === null) {
            return User::create([
                'name' => $request->contact_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'city' => $request->city,
                // Nobody knows this, including us. The invite sets a real one.
                'password' => Str::password(40),
                'role' => 'shop_owner',
            ]);
        }

        // An administrator who happens to share the address must not be demoted
        // to shop owner by approving an application — `role` holds one value.
        if ($existing->isAdmin()) {
            throw ValidationException::withMessages([
                'request' => $request->email.' belongs to an administrator. Use the shop\'s own address.',
            ]);
        }

        $existing->forceFill(['role' => 'shop_owner'])->save();

        return $existing;
    }

    private function shop(ShopRequest $request, ?User $by): Shop
    {
        $shop = Shop::create([
            'name' => $request->business_name,
            'slug' => $this->slug($request->business_name),
            'city' => $request->city,
            'address' => $request->address,
            'phone' => $request->phone,
            'status' => 'pending',
        ]);

        // Starter is the free plan and the default a new shop should land on:
        // nothing to invoice, 5% commission, and the owner can move up whenever
        // they want to. `Billing` owns the rate, so it is set there and not here.
        app(Billing::class)->subscribe($shop, Plan::where('code', 'starter')->firstOrFail());

        app(ShopApproval::class)->approve($shop, $by);

        return $shop;
    }

    /** `inkwell-press`, then `inkwell-press-2` if a shop already has that name. */
    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $suffix = 2;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** The same link "forgot password" sends, built rather than mailed. */
    private function setPasswordLink(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }
}
