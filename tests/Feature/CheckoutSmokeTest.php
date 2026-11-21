<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The checkout page must never 500 on a bad URL.
 *
 * Found during a smoke pass: `show()` called the calculator outside a try/catch
 * while `store()` already handled it, so a stale or hand-edited link produced a
 * server error page instead of a usable screen.
 */
class CheckoutSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_missing_service_id_redirects_instead_of_500(): void
    {
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/shops/inkwell-press/checkout?service_id=99999')
            ->assertRedirect('/shops/inkwell-press/build');
    }

    public function test_a_size_outside_every_tier_redirects_instead_of_500(): void
    {
        // Halftone Hub's largest tier tops out at 4 inches.
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/shops/halftone-hub/checkout?width_in=900&height_in=900')
            ->assertRedirect('/shops/halftone-hub/build');
    }

    public function test_a_shop_with_no_catalogue_returns_404(): void
    {
        $empty = Shop::create([
            'name' => 'Empty Press',
            'slug' => 'empty-press',
            'city' => 'Manila',
            'address' => '1 Nowhere St',
            'status' => 'active',
            'plan' => 'starter',
            'commission_rate' => 500,
        ]);

        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get("/shops/{$empty->slug}/checkout")
            ->assertNotFound();
    }

    public function test_a_valid_checkout_still_renders(): void
    {
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/shops/inkwell-press/checkout?width_in=36&height_in=48&qty=2')
            ->assertSuccessful()
            ->assertSee('Inkwell Press');
    }
}
