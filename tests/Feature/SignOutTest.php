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
}
