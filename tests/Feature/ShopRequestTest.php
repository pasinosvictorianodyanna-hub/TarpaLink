<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ShopRequests\Pages\ListShopRequests;
use App\Filament\Admin\Resources\ShopRequests\Pages\ViewShopRequest;
use App\Mail\ShopInvited;
use App\Models\Shop;
use App\Models\ShopRequest;
use App\Models\User;
use App\Services\ShopOnboarding;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A shop asking to be let in, and the administrator letting them in.
 *
 * The important half is the negative space: a request must create **no account
 * and no shop**. The page says so, the form says so, and these tests are what
 * keep it true — an application form that quietly creates users is how a
 * platform ends up with accounts nobody approved.
 */
class ShopRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // The throttle is per IP and the test process shares a cache, so each
        // test that exercises it claims its own address.
        RateLimiter::clear('shop-request');
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bayanihan Press',
            'contact_name' => 'Rosa Mendoza',
            'email' => 'rosa@bayanihan.ph',
            'phone' => '0917 555 0100',
            'city' => 'Quezon City',
            'address' => '88 Maginhawa Street, Sikatuna Village',
            'services' => "Tarpaulin\nFlyers\nStickers",
            'message' => 'Two large-format printers, ten years trading.',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ], $overrides);
    }

    /**
     * A request row with no account behind it — the older admission shape, where
     * approval still has to create the owner and hand over a link.
     */
    private function request(array $overrides = []): ShopRequest
    {
        $form = $this->form($overrides);
        unset($form['password'], $form['password_confirmation']);

        return ShopRequest::create($form + ['status' => 'new']);
    }

    // ------------------------------------------------------------ the form

    public function test_the_page_offers_the_request_form(): void
    {
        $this->get('/open-a-shop')
            ->assertOk()
            ->assertSee('Request a shop account')
            ->assertSee('name="business_name"', false)
            ->assertSee('name="password"', false)
            ->assertSee(route('shop-request.store'), false);
    }

    /**
     * Applying creates the account and the request — and still no shop.
     *
     * The account is the change: the applicant chooses a password here, which
     * is what removes mail delivery from the critical path. The shop is still
     * the administrator's to create.
     */
    public function test_applying_creates_the_account_and_the_request_but_no_shop(): void
    {
        $shops = Shop::count();

        $this->post('/open-a-shop', $this->form())
            ->assertRedirect(route('console.waiting'));

        $request = ShopRequest::sole();

        $this->assertSame('Bayanihan Press', $request->business_name);
        $this->assertSame('new', $request->status);
        $this->assertNull($request->shop_id);

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->assertSame('shop_owner', $owner->role);
        $this->assertSame($owner->id, $request->user_id);

        // The promise on the page, enforced.
        $this->assertSame($shops, Shop::count(), 'Applying must not create a shop.');
        $this->assertSame(0, $owner->shops()->count(), 'An unapproved owner must have no shop.');

        // Signed in by applying, so the waiting room is reachable straight away.
        $this->assertAuthenticatedAs($owner);
    }

    public function test_the_form_needs_the_fields_an_admin_cannot_guess(): void
    {
        $this->post('/open-a-shop', ['business_name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['business_name', 'contact_name', 'email', 'city', 'address', 'password']);

        $this->assertSame(0, ShopRequest::count());
        $this->assertSame(0, User::where('email', 'not-an-email')->count());
    }

    /** A bot fills every input it can see; a person never meets the hidden one. */
    public function test_a_filled_honeypot_is_accepted_silently_and_stored_nowhere(): void
    {
        $users = User::count();

        $this->post('/open-a-shop', $this->form(['website' => 'http://spam.example']))
            ->assertRedirect(route('console.waiting'));

        $this->assertSame(0, ShopRequest::count());
        $this->assertSame($users, User::count(), 'A trapped submission must not create an account either.');
        $this->assertGuest();
    }

    public function test_a_signed_in_applicant_is_not_asked_for_a_password(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)->post('/open-a-shop', $this->form([
            'email' => 'jomar@tarpalink.ph',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertRedirect(route('console.waiting'));

        $request = ShopRequest::sole();

        $this->assertSame($customer->id, $request->user_id);
        $this->assertSame('shop_owner', $customer->fresh()->role);
        $this->assertSame(1, User::where('email', 'jomar@tarpalink.ph')->count(), 'The account must be reused.');
    }

    public function test_a_second_waiting_request_is_refused(): void
    {
        $this->post('/open-a-shop', $this->form());

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->actingAs($owner)
            ->post('/open-a-shop', $this->form())
            ->assertSessionHasErrors('business_name');

        $this->assertSame(1, ShopRequest::count(), 'One shop account, one open request.');
    }

    /** `role` holds one value, so approving would demote them out of the panel. */
    public function test_an_administrator_cannot_apply(): void
    {
        $this->actingAs($this->admin())
            ->post('/open-a-shop', $this->form(['email' => 'sofia@tarpalink.ph']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, ShopRequest::count());
    }

    public function test_the_public_form_is_rate_limited(): void
    {
        // A distinct address so other tests' submissions do not share the bucket.
        $server = ['REMOTE_ADDR' => '203.0.113.77'];

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables($server)
                ->post('/open-a-shop', $this->form(['email' => "shop{$i}@example.ph"]))
                ->assertRedirect();

            // Applying signs the applicant in, and `throttle` keys on the user
            // id once there is one — so without this the sixth attempt would
            // land in a fresh bucket and never trip the limit.
            Auth::logout();
        }

        $this->withServerVariables($server)
            ->post('/open-a-shop', $this->form(['email' => 'shop6@example.ph']))
            ->assertStatus(429);
    }

    // ---------------------------------------------------------- the queue

    public function test_only_an_admin_can_open_the_queue(): void
    {
        $this->actingAs($this->admin())->get('/admin/shop-requests')->assertOk();
        $this->actingAs($this->owner())->get('/admin/shop-requests')->assertForbidden();
    }

    public function test_the_queue_lists_what_is_waiting(): void
    {
        $this->request(['business_name' => 'Bayanihan Press']);

        Livewire::actingAs($this->admin())
            ->test(ListShopRequests::class)
            ->assertOk()
            ->assertSee('Bayanihan Press')
            ->assertSee('Rosa Mendoza');
    }

    // -------------------------------------------------------- the decision

    public function test_approving_creates_the_owner_the_shop_and_the_invite(): void
    {
        Mail::fake();

        $request = $this->request();

        $result = app(ShopOnboarding::class)->approve($request, $this->admin());

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->assertSame('shop_owner', $owner->role);
        $this->assertSame('Rosa Mendoza', $owner->name);

        $shop = $result['shop'];
        $this->assertSame('Bayanihan Press', $shop->name);
        $this->assertSame('bayanihan-press', $shop->slug);
        $this->assertSame('active', $shop->status);
        $this->assertSame('starter', $shop->plan);
        $this->assertSame(500, $shop->commission_rate);
        $this->assertNotNull($shop->approved_at);

        // The pivot is what makes the console open.
        $this->assertSame('owner', $owner->shops()->whereKey($shop->id)->firstOrFail()->pivot->role);

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($shop->id, $request->shop_id);
        $this->assertSame($this->admin()->id, $request->handled_by);
        $this->assertNotNull($request->handled_at);

        Mail::assertSent(ShopInvited::class, fn (ShopInvited $mail) => $mail->hasTo('rosa@bayanihan.ph'));
    }

    public function test_the_new_owner_can_open_the_console(): void
    {
        Mail::fake();

        app(ShopOnboarding::class)->approve($this->request(), $this->admin());

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->actingAs($owner)
            ->get('/console')
            ->assertOk()
            ->assertSee('Bayanihan Press');
    }

    /**
     * The end of the story the invite starts: the link in the email is a real
     * password reset, so the owner chooses their own password and can sign in.
     */
    public function test_the_invitation_link_sets_a_password_that_works(): void
    {
        Mail::fake();

        $result = app(ShopOnboarding::class)->approve($this->request(), $this->admin());

        $path = parse_url($result['link'], PHP_URL_PATH).'?'.parse_url($result['link'], PHP_URL_QUERY);

        $this->get($path)->assertOk();

        $this->post(route('password.store'), [
            'token' => basename(parse_url($result['link'], PHP_URL_PATH)),
            'email' => 'rosa@bayanihan.ph',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertRedirect();

        $this->post('/login', [
            'email' => 'rosa@bayanihan.ph',
            'password' => 'a-good-password',
        ])->assertRedirect('/console');
    }

    public function test_an_existing_account_is_reused_and_promoted(): void
    {
        Mail::fake();

        // Somebody who ordered as a customer first, using the same address.
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
        $this->assertSame('customer', $customer->role);

        app(ShopOnboarding::class)->approve($this->request(['email' => 'jomar@tarpalink.ph']), $this->admin());

        $this->assertSame(1, User::where('email', 'jomar@tarpalink.ph')->count());
        $this->assertSame('shop_owner', $customer->fresh()->role);
        $this->assertSame('Bayanihan Press', $customer->fresh()->currentShop()?->name);
    }

    /** `role` holds one value, so promoting an admin would demote them. */
    public function test_an_administrators_address_is_refused(): void
    {
        Mail::fake();

        $request = $this->request(['email' => 'sofia@tarpalink.ph']);

        try {
            app(ShopOnboarding::class)->approve($request, $this->admin());
            $this->fail('Approving an administrator address should be refused.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('request', $e->errors());
        }

        $this->assertSame('admin', $this->admin()->fresh()->role);
        $this->assertSame('new', $request->fresh()->status);
        $this->assertNull($request->fresh()->shop_id);
        Mail::assertNothingSent();
    }

    public function test_a_request_cannot_be_approved_twice(): void
    {
        Mail::fake();

        $request = $this->request();
        app(ShopOnboarding::class)->approve($request, $this->admin());

        $shops = Shop::count();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(ShopOnboarding::class)->approve($request->fresh(), $this->admin());
        } finally {
            $this->assertSame($shops, Shop::count(), 'A second approval must not create a second shop.');
        }
    }

    public function test_approving_from_the_queue_does_the_same_thing(): void
    {
        Mail::fake();

        $request = $this->request();

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseHas('shops', ['slug' => 'bayanihan-press', 'status' => 'active']);
        Mail::assertSent(ShopInvited::class);
    }

    // ------------------------------------------------ the self-registered path

    /**
     * The whole point of the change: an applicant who chose a password needs
     * nothing handed to them. Approval attaches the shop and stops.
     */
    public function test_approving_a_self_registered_request_attaches_the_shop_and_sends_nothing(): void
    {
        Mail::fake();

        $this->post('/open-a-shop', $this->form());

        $request = ShopRequest::sole();
        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $result = app(ShopOnboarding::class)->approve($request, $this->admin());

        $this->assertFalse($result['invited']);
        $this->assertNull($result['link']);
        Mail::assertNothingSent();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($owner->id, $request->fresh()->user_id);
        $this->assertSame('owner', $owner->shops()->whereKey($result['shop']->id)->firstOrFail()->pivot->role);

        // No token minted: the password they chose is the one they keep.
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'rosa@bayanihan.ph')->count());
    }

    public function test_the_password_chosen_at_signup_signs_the_owner_in(): void
    {
        Mail::fake();

        $this->post('/open-a-shop', $this->form());
        app(ShopOnboarding::class)->approve(ShopRequest::sole(), $this->admin());

        Auth::logout();

        $this->post('/login', [
            'email' => 'rosa@bayanihan.ph',
            'password' => 'a-good-password',
        ])->assertRedirect(route('console.dashboard'));
    }

    public function test_a_self_registered_request_offers_no_resend(): void
    {
        Mail::fake();

        $this->post('/open-a-shop', $this->form());
        $request = ShopRequest::sole();
        app(ShopOnboarding::class)->approve($request, $this->admin());

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->fresh()->getRouteKey()])
            ->assertActionHidden(TestAction::make('resend'));
    }

    // -------------------------------------------------------- the waiting room

    public function test_a_pending_owner_waits_instead_of_hitting_a_403(): void
    {
        $this->post('/open-a-shop', $this->form());

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->actingAs($owner)
            ->get('/console/waiting')
            ->assertOk()
            ->assertSee('Waiting for approval')
            ->assertSee('Bayanihan Press');

        // Every other console URL sends them back to the room, not to a 403.
        $this->actingAs($owner)
            ->get('/console')
            ->assertRedirect(route('console.waiting'));

        $this->actingAs($owner)
            ->get('/console/jobs')
            ->assertRedirect(route('console.waiting'));
    }

    public function test_approval_opens_the_console_from_the_waiting_room(): void
    {
        Mail::fake();

        $this->post('/open-a-shop', $this->form());
        app(ShopOnboarding::class)->approve(ShopRequest::sole(), $this->admin());

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->actingAs($owner)->get('/console/waiting')->assertRedirect(route('console.dashboard'));
        $this->actingAs($owner)->get('/console')->assertOk()->assertSee('Bayanihan Press');
    }

    public function test_a_rejected_request_shows_the_reason(): void
    {
        $this->post('/open-a-shop', $this->form());

        $request = ShopRequest::sole();

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('reject', data: ['reason' => 'Business permit could not be verified.']);

        $owner = User::where('email', 'rosa@bayanihan.ph')->firstOrFail();

        $this->actingAs($owner)
            ->get('/console/waiting')
            ->assertOk()
            ->assertSee('not approved')
            ->assertSee('Business permit could not be verified.');
    }

    // --------------------------------------------------------- the re-send

    public function test_a_resend_mints_a_fresh_link_and_emails_it(): void
    {
        Mail::fake();

        $request = $this->request();
        $first = app(ShopOnboarding::class)->approve($request, $this->admin());

        $shops = Shop::count();
        $users = User::count();

        $second = app(ShopOnboarding::class)->reinvite($request->fresh());

        $this->assertNotSame($first['link'], $second['link'], 'A re-send must mint a new token.');
        $this->assertSame($first['shop']->id, $second['shop']->id, 'A re-send must not create a shop.');
        $this->assertSame($first['user']->id, $second['user']->id, 'A re-send must not create an account.');
        $this->assertSame($shops, Shop::count());
        $this->assertSame($users, User::count());

        $this->assertCount(2, Mail::sent(ShopInvited::class));
        Mail::assertSent(ShopInvited::class, fn (ShopInvited $mail) => $mail->hasTo('rosa@bayanihan.ph'));
    }

    /**
     * Re-sending is not additive: `createToken()` overwrites the row, so the
     * link in the first email stops working. One account, one way in.
     */
    public function test_a_resend_retires_the_previous_link(): void
    {
        Mail::fake();

        $request = $this->request();
        $first = app(ShopOnboarding::class)->approve($request, $this->admin());
        $owner = $first['user'];

        $second = app(ShopOnboarding::class)->reinvite($request->fresh());

        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'rosa@bayanihan.ph')->count());

        $this->post(route('password.store'), [
            'token' => basename(parse_url($first['link'], PHP_URL_PATH)),
            'email' => 'rosa@bayanihan.ph',
            'password' => 'from-the-stale-link',
            'password_confirmation' => 'from-the-stale-link',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('from-the-stale-link', $owner->fresh()->password));

        $this->post(route('password.store'), [
            'token' => basename(parse_url($second['link'], PHP_URL_PATH)),
            'email' => 'rosa@bayanihan.ph',
            'password' => 'from-the-new-link',
            'password_confirmation' => 'from-the-new-link',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('from-the-new-link', $owner->fresh()->password));
    }

    public function test_only_an_approved_request_offers_a_resend(): void
    {
        Mail::fake();

        $request = $this->request();

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->getRouteKey()])
            ->assertActionHidden(TestAction::make('resend'));

        app(ShopOnboarding::class)->approve($request, $this->admin());

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->fresh()->getRouteKey()])
            ->assertActionVisible(TestAction::make('resend'));
    }

    /** The shop is `nullOnDelete`; an approved request can outlive the shop. */
    public function test_a_resend_is_refused_when_the_shop_is_gone(): void
    {
        Mail::fake();

        $request = $this->request();
        app(ShopOnboarding::class)->approve($request, $this->admin());

        $request->fresh()->forceFill(['shop_id' => null])->save();

        try {
            app(ShopOnboarding::class)->reinvite($request->fresh());
            $this->fail('Re-inviting a request with no shop should be refused.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('request', $e->errors());
        }

        $this->assertCount(1, Mail::sent(ShopInvited::class), 'Only the approval should have mailed.');
    }

    public function test_resending_from_the_request_page_flashes_the_new_link(): void
    {
        Mail::fake();

        $request = $this->request();
        app(ShopOnboarding::class)->approve($request, $this->admin());

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->fresh()->getRouteKey()])
            ->callAction('resend')
            ->assertHasNoActionErrors();

        $this->assertCount(2, Mail::sent(ShopInvited::class));
        $this->assertNotNull(session('invite_link'), 'The new link must be shown, not only mailed.');
        $this->assertSame('rosa@bayanihan.ph', session('invite_email'));
    }

    public function test_rejecting_stores_the_reason_and_creates_nothing(): void
    {
        Mail::fake();

        $request = $this->request();
        $shops = Shop::count();
        $users = User::count();

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('reject', data: ['reason' => 'Business permit could not be verified.'])
            ->assertHasNoActionErrors();

        $request->refresh();

        $this->assertSame('rejected', $request->status);
        $this->assertSame('Business permit could not be verified.', $request->reason);
        $this->assertSame($shops, Shop::count());
        $this->assertSame($users, User::count());
        Mail::assertNothingSent();
    }

    public function test_a_handled_request_offers_no_second_decision(): void
    {
        Mail::fake();

        $request = $this->request();
        app(ShopOnboarding::class)->approve($request, $this->admin());

        Livewire::actingAs($this->admin())
            ->test(ViewShopRequest::class, ['record' => $request->fresh()->getRouteKey()])
            ->assertActionHidden(TestAction::make('approve'))
            ->assertActionHidden(TestAction::make('reject'));
    }
}
