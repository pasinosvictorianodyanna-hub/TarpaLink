<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where each role lands after signing in.
 *
 * Every role used to be sent to /account — the customer's job list. A shop owner
 * and an admin signed in and saw an empty "My jobs", with nothing on the page
 * saying that the screen they wanted was somewhere else. The redirect is now
 * decided by role, and the header follows the same rule so the wrong screen is
 * never one click away.
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function login(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => 'password']);
    }

    public function test_a_customer_lands_on_their_jobs(): void
    {
        $this->login('jomar@tarpalink.ph')->assertRedirect(route('account'));
    }

    public function test_a_shop_owner_lands_on_the_console(): void
    {
        $this->login('kevin@inkwell.ph')->assertRedirect(route('console.dashboard'));
    }

    public function test_an_admin_lands_on_the_admin_panel(): void
    {
        $this->login('sofia@tarpalink.ph')->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_a_shop_owner_whose_shop_is_suspended_falls_back_to_the_storefront(): void
    {
        // The console refuses a suspended shop, so sending the owner there would
        // be a 403 one second after a successful sign-in.
        Shop::where('slug', 'inkwell-press')->firstOrFail()->forceFill(['status' => 'suspended'])->save();

        $this->login('kevin@inkwell.ph')->assertRedirect(route('account'));
    }

    public function test_the_page_you_were_bounced_from_still_wins(): void
    {
        // A shop owner who followed a link to their profile goes back to the
        // profile, not to the console.
        $this->get('/account/profile')->assertRedirect('/login');

        $this->post('/login', ['email' => 'kevin@inkwell.ph', 'password' => 'password'])
            ->assertRedirect(route('profile.edit'));
    }

    public function test_the_header_offers_each_role_its_own_door(): void
    {
        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/')
            ->assertOk()
            ->assertSee('Console')
            ->assertDontSee('My jobs');

        $this->actingAs(User::where('email', 'sofia@tarpalink.ph')->firstOrFail())
            ->get('/')
            ->assertOk()
            ->assertSee('Admin panel')
            ->assertDontSee('My jobs');

        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/')
            ->assertOk()
            ->assertSee('My jobs')
            ->assertDontSee('Admin panel');
    }

    public function test_the_proof_bell_is_for_customers_only(): void
    {
        // For a shop owner the bell counted proofs waiting on them as a customer,
        // which is zero forever, and it led to a screen they do not use.
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/')
            ->assertOk()
            ->assertSee('Jobs waiting on you');

        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Jobs waiting on you');
    }
}
