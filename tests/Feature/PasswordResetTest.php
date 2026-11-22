<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Password recovery.
 *
 * There was none: a user who forgot their password had no way back into their
 * account, and no screen anywhere said so. The two things worth holding to are
 * that a working link actually changes the password, and that asking about an
 * address tells the asker nothing about whether it has an account here.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    public function test_the_sign_in_page_offers_a_way_to_recover_a_password(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee(route('password.request'), false);
    }

    public function test_the_request_form_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Send reset link');
    }

    public function test_a_registered_address_is_sent_a_reset_link(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'jomar@tarpalink.ph'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $this->customer(),
            ResetPassword::class,
            fn (ResetPassword $notification) => $notification->token !== '',
        );
    }

    /**
     * The enumeration guard. Laravel's broker reports "we can't find a user with
     * that email address", which turns this form into a way to check whether any
     * given address has an account.
     */
    public function test_an_unknown_address_gets_the_same_answer_as_a_known_one(): void
    {
        Notification::fake();

        $known = $this->post('/forgot-password', ['email' => 'jomar@tarpalink.ph']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();

        $this->assertSame(
            $known->getSession()->get('status'),
            $unknown->getSession()->get('status'),
        );

        Notification::assertCount(1);
    }

    public function test_a_valid_token_changes_the_password(): void
    {
        $user = $this->customer();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));

        // And the new password actually gets them in.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'a-brand-new-password',
        ])->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_bad_token_is_refused(): void
    {
        $user = $this->customer();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');

        // The old password still works, which is the point of refusing.
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_reset_form_carries_the_token_and_the_address(): void
    {
        $user = $this->customer();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($token)
            ->assertSee($user->email);
    }

    public function test_the_password_must_be_confirmed_and_long_enough(): void
    {
        $user = $this->customer();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'something-else-entirely',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /**
     * Somebody resets a password because they think another person has it. A
     * "remember me" cookie issued before the reset would keep that person signed
     * in, which would make the reset pointless.
     */
    public function test_a_reset_invalidates_existing_remember_cookies(): void
    {
        $user = $this->customer();
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();

        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('login'));

        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_a_signed_in_user_is_not_offered_the_reset_form(): void
    {
        $this->actingAs($this->customer())
            ->get('/forgot-password')
            ->assertRedirect();
    }

    public function test_the_request_form_is_throttled(): void
    {
        Notification::fake();

        foreach (range(1, 6) as $ignored) {
            $this->post('/forgot-password', ['email' => 'jomar@tarpalink.ph']);
        }

        // The seventh is stopped by the route throttle, not by the broker — the
        // broker only throttles per account, which does nothing about somebody
        // working through a list of addresses.
        $this->post('/forgot-password', ['email' => 'jomar@tarpalink.ph'])
            ->assertStatus(429);
    }
}
