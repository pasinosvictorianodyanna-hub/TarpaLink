<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The customer's front door, required by the course rubric's "User Registration
 * — Sign Up / Login" item.
 *
 * The important assertion is not that the form renders: it is that the account
 * it creates is a customer. A public form that accepts `role=admin` would turn
 * a sign-up page into a privilege escalation.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** @return array<string, string> */
    private function details(): array
    {
        return [
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ];
    }

    public function test_the_registration_page_is_reachable_and_links_back_to_sign_in(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create an account')
            ->assertSee(route('login'), false);
    }

    public function test_a_visitor_can_register_and_lands_on_their_own_account(): void
    {
        $response = $this->post('/register', $this->details());

        $user = User::where('email', 'ana.reyes@example.com')->firstOrFail();

        $response->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertTrue(Hash::check('secret12345', $user->password));
    }

    public function test_a_registration_cannot_choose_an_admin_or_owner_role(): void
    {
        $this->post('/register', array_merge($this->details(), ['role' => 'admin']));

        $user = User::where('email', 'ana.reyes@example.com')->firstOrFail();

        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isShopOwner());
    }

    public function test_a_duplicate_email_is_rejected_even_though_profile_updates_allow_keeping_your_own(): void
    {
        $this->from('/register')
            ->post('/register', array_merge($this->details(), ['email' => 'jomar@tarpalink.ph']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_password_must_be_confirmed_and_long_enough(): void
    {
        $this->post('/register', array_merge($this->details(), ['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');

        $this->post('/register', array_merge($this->details(), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'ana.reyes@example.com')->first());
    }

    public function test_a_signed_in_user_is_not_offered_a_second_registration(): void
    {
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/register')
            ->assertRedirect(route('account'));

        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->post('/register', array_merge($this->details(), ['email' => 'another@example.com']))
            ->assertRedirect(route('account'));

        $this->assertNull(User::where('email', 'another@example.com')->first());
    }

    public function test_the_public_nav_and_login_page_both_offer_sign_up(): void
    {
        $this->get('/')
            ->assertSee(route('register'), false)
            ->assertSee('Sign up');

        $this->get('/login')
            ->assertSee(route('register'), false)
            ->assertSee('Create an account');
    }
}
