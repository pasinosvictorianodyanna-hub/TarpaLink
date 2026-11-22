<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The storefront sign-in is the only unauthenticated door into an account, and
 * it had no ceiling on it: a script could try passwords as fast as the network
 * allowed.
 *
 * Filament's two panel logins already throttle themselves, so this file covers
 * `/login` only — the one door that was open.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // The suite runs on the array cache, so each test gets a fresh limiter.
        // Cleared anyway because that is an accident of phpunit.xml, not a
        // promise: the day somebody switches the test cache to a persistent
        // store, this file should keep passing rather than become order-dependent.
        RateLimiter::clear('jomar@tarpalink.ph|127.0.0.1');
    }

    private function attempt(string $email, string $password = 'wrong-password'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_five_wrong_passwords_are_allowed_and_the_sixth_is_refused(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->attempt('jomar@tarpalink.ph')
                ->assertSessionHasErrors('email');

            $this->assertStringContainsString(
                'do not match',
                session('errors')->first('email'),
                "Attempt {$attempt} should be a plain wrong-password failure.",
            );
        }

        $this->attempt('jomar@tarpalink.ph')
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many sign-in attempts',
            session('errors')->first('email'),
        );
    }

    public function test_the_right_password_is_refused_while_locked_out(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->attempt('jomar@tarpalink.ph');
        }

        // The whole point of the lockout: knowing the password is not enough
        // while the limiter is still counting.
        $this->attempt('jomar@tarpalink.ph', 'password')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_successful_sign_in_clears_the_count(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->attempt('jomar@tarpalink.ph');
        }

        $this->attempt('jomar@tarpalink.ph', 'password')
            ->assertRedirect(route('account'));

        $this->assertAuthenticated();

        // A customer who fat-fingers four times and then remembers their
        // password should not be one slip away from a lockout for the rest of
        // the minute.
        $this->post('/logout');

        foreach (range(1, 5) as $attempt) {
            $this->attempt('jomar@tarpalink.ph');

            $this->assertStringContainsString(
                'do not match',
                session('errors')->first('email'),
                "Attempt {$attempt} after a success should start from a clean count.",
            );
        }
    }

    public function test_another_address_is_not_locked_out_by_someone_elses_attempts(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->attempt('jomar@tarpalink.ph');
        }

        // Same IP, different account: the key includes the email, so this
        // customer is unaffected by the noise about the other one.
        $this->attempt('renz@example.com', 'password')->assertRedirect(route('account'));
    }

    public function test_an_administrator_probe_counts_towards_the_limit(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        foreach (range(1, 5) as $ignored) {
            $this->attempt($admin->email);
        }

        // Refusing admin sign-in here is still a failed sign-in. If it were not
        // counted, the limiter would be no obstacle to somebody enumerating
        // which addresses belong to administrators.
        $this->attempt($admin->email, 'password')
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many sign-in attempts',
            session('errors')->first('email'),
        );
    }
}
