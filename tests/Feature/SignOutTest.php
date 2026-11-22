<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A signed-in customer must be able to sign out from anywhere.
 *
 * This exists because they could not: the only sign-out control was on the
 * profile screen. Filament answers 403 — not a redirect to its login — to an
 * authenticated user without panel access, so a customer who opened /admin had
 * no way to switch to an administrator account short of clearing cookies.
 */
class SignOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_the_header_offers_a_sign_out_control_to_a_signed_in_user(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)
            ->get('/')
            ->assertOk()
            ->assertSee('Sign out');
    }

    public function test_a_guest_is_not_offered_sign_out(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Sign out');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Every dashboard has to offer a way out.
     *
     * Filament keeps its own sign-out behind the avatar menu — a click on an
     * unlabelled picture — and while the panel theme was uncompiled there was
     * nothing to click at all. Both panels now render a labelled button in the
     * topbar through a render hook, and the console has one in its sidebar
     * footer. The marker is `data-sign-out`, so this asserts the hook rendered
     * rather than counting the word "Sign out" on the page.
     *
     * The two panels are checked in separate tests on purpose. Signing in as a
     * second user inside one test leaves the first user's password hash in the
     * session, and Filament's AuthenticateSession middleware reads that mismatch
     * as a stolen cookie and redirects to the login page — which looks exactly
     * like a broken panel.
     */
    public function test_the_shop_panel_offers_a_labelled_sign_out(): void
    {
        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/shop')
            ->assertOk()
            ->assertSee('data-sign-out', false);
    }

    public function test_the_admin_panel_offers_a_labelled_sign_out(): void
    {
        $this->actingAs(User::where('email', 'sofia@tarpalink.ph')->firstOrFail())
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-sign-out', false);
    }

    public function test_the_console_carries_a_sign_out_route_and_token(): void
    {
        // The console's button is inside the Vue component, so the server can
        // only hand it the destination and the token. Asserting those is the
        // strongest check available without a browser.
        $props = $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/console')
            ->assertOk()
            ->viewData('props');

        $this->assertSame(route('logout'), $props['routes']['logout']);
        $this->assertNotEmpty($props['csrf']);
    }
}
