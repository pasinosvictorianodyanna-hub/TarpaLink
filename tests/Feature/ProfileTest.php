<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #16 (profile screen).
 *
 * The two writes are tested separately because they have different rules: the
 * details form may save without proving anything, the password form may not.
 */
class ProfileTest extends TestCase
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

    /**
     * The props the island is mounted with, decoded the way the browser will.
     *
     * @return array<string, mixed>
     */
    private function props(string $html): array
    {
        preg_match('/data-props="([^"]*)"/', $html, $matches);

        return json_decode(html_entity_decode($matches[1] ?? '{}'), true) ?: [];
    }

    public function test_a_guest_cannot_open_the_profile(): void
    {
        $this->get('/account/profile')->assertRedirect('/login');
    }

    public function test_the_profile_page_mounts_the_island_with_the_current_values(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertSuccessful()
            ->assertSee('data-component="profile-form"', false);

        // The island is client-rendered, so the server's contract is the props:
        // the current values and the two endpoints. The fields themselves are
        // Vue's job, which is why there is no assertSee for "Personal details"
        // here — that string is not in this HTML at all.
        $props = $this->props($response->getContent());

        $this->assertSame($user->name, $props['user']['name']);
        $this->assertSame($user->phone, $props['user']['phone']);
        $this->assertSame($user->city, $props['user']['city']);
        $this->assertSame(route('api.profile.update'), $props['updateUrl']);
        $this->assertSame(route('api.profile.password'), $props['passwordUrl']);
        $this->assertNotNull($props['passwordChangedAt']);
    }

    public function test_saving_details_updates_the_user(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'name' => 'Jomar M. Mendoza',
                'email' => 'jomar@tarpalink.ph',
                'phone' => '+63 917 000 1234',
                'city' => 'Pasig',
            ])
            ->assertSuccessful()
            ->assertJsonPath('user.city', 'Pasig');

        $user->refresh();

        $this->assertSame('Jomar M. Mendoza', $user->name);
        $this->assertSame('+63 917 000 1234', $user->phone);
        $this->assertSame('Pasig', $user->city);
    }

    public function test_keeping_your_own_email_is_not_a_duplicate(): void
    {
        $user = $this->customer();

        // The unique rule has to ignore this user's own row, or saving the form
        // without touching the email would always fail.
        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
            ])
            ->assertSuccessful();
    }

    public function test_another_users_email_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->patchJson('/api/profile', [
                'name' => 'Jomar Mendoza',
                'email' => 'kevin@inkwell.ph',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->patchJson('/api/profile', ['name' => '', 'email' => 'jomar@tarpalink.ph'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = $this->customer();
        $before = $user->password;

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'not-my-password',
                'password' => 'a-new-secret',
                'password_confirmation' => 'a-new-secret',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame($before, $user->fresh()->password, 'The hash must not change on a wrong current password.');
    }

    public function test_changing_the_password_updates_the_hash_and_the_timestamp(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'a-new-secret',
                'password_confirmation' => 'a-new-secret',
            ])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue(Hash::check('a-new-secret', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $this->actingAs($this->customer())
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'a-new-secret',
                'password_confirmation' => 'a-different-secret',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_a_guest_cannot_write_to_either_endpoint(): void
    {
        $this->patchJson('/api/profile', ['name' => 'Nobody', 'email' => 'nobody@example.com'])
            ->assertStatus(401);

        $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'a-new-secret',
            'password_confirmation' => 'a-new-secret',
        ])->assertStatus(401);
    }
}
