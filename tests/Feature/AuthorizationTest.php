<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #9.
 *
 * These assertions are made against the Gate, not against a controller, because
 * the point is that the rule holds everywhere — the panel, the storefront and any
 * future entry point all ask the same policy.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function halftone(): Shop
    {
        return Shop::where('slug', 'halftone-hub')->firstOrFail();
    }

    public function test_a_customer_cannot_open_the_shop_panel(): void
    {
        $this->actingAs($this->user('jomar@tarpalink.ph'))
            ->get('/shop')
            ->assertForbidden();
    }

    public function test_a_shop_owner_cannot_edit_another_shops_price_matrix(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $otherService = $this->halftone()->services()->firstOrFail();

        $this->assertFalse($owner->can('update', $otherService));
        $this->assertFalse($owner->can('delete', $otherService));

        // And the panel's scoped query turns the direct URL into a 404.
        $this->actingAs($owner)
            ->get("/shop/services/{$otherService->id}/edit")
            ->assertNotFound();

        // Their own service is editable, so the rule is not "nobody can".
        $ownService = $this->inkwell()->services()->firstOrFail();
        $this->assertTrue($owner->can('update', $ownService));
    }

    public function test_a_tier_of_another_shops_service_is_protected_too(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $foreignTier = $this->halftone()->services()->firstOrFail()->tiers()->firstOrFail();

        $this->assertTrue($owner->can('create', \App\Models\PriceTier::class));
        $this->assertFalse($owner->can('update', $foreignTier));
    }

    /**
     * Site content is no longer this application's to protect.
     *
     * The copy now lives in the CMS, which is a separate application with its
     * own administrators. What the engine still owes is that nobody signs in
     * here and finds an admin screen they are not entitled to — asserted below
     * and in PanelAccessTest.
     */
    public function test_a_shop_owner_cannot_reach_the_admin_panel(): void
    {
        $this->actingAs($this->user('kevin@inkwell.ph'))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_customer_cannot_read_another_customers_job(): void
    {
        $jomar = $this->user('jomar@tarpalink.ph');
        $otherJob = Job::where('user_id', '!=', $jomar->id)->firstOrFail();

        $this->assertFalse($jomar->can('view', $otherJob));

        $this->actingAs($jomar)
            ->get("/jobs/{$otherJob->reference}")
            ->assertNotFound();

        $ownJob = Job::where('user_id', $jomar->id)->firstOrFail();
        $this->assertTrue($jomar->can('view', $ownJob));

        $this->actingAs($jomar)
            ->get("/jobs/{$ownJob->reference}")
            ->assertSuccessful();
    }

    public function test_a_shop_can_read_its_own_jobs_but_not_another_shops(): void
    {
        $owner = $this->user('kevin@inkwell.ph');

        $ownJob = $this->inkwell()->jobs()->firstOrFail();
        $foreignJob = $this->halftone()->jobs()->firstOrFail();

        $this->assertTrue($owner->can('view', $ownJob));
        $this->assertFalse($owner->can('view', $foreignJob));
    }

    public function test_only_an_administrator_can_approve_a_shop(): void
    {
        $pending = Shop::where('status', 'pending')->firstOrFail();

        $this->assertFalse($this->user('kevin@inkwell.ph')->can('approve', $pending));
        $this->assertTrue($this->user('sofia@tarpalink.ph')->can('approve', $pending));
    }

    public function test_an_administrator_cannot_delete_their_own_account(): void
    {
        $admin = $this->user('sofia@tarpalink.ph');
        $other = $this->user('jomar@tarpalink.ph');

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertTrue($admin->can('delete', $other));
    }

    public function test_a_guest_sees_nothing_protected(): void
    {
        $this->get('/shop')->assertRedirect('/shop/login');

        $job = Job::firstOrFail();
        $this->get("/jobs/{$job->reference}")->assertNotFound();
    }

    public function test_there_is_no_blanket_administrator_bypass(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $admin = $this->user('sofia@tarpalink.ph');

        // A shop owner gets none of the platform abilities...
        $this->assertFalse($owner->can('create', \App\Models\Plan::class));
        $this->assertFalse($owner->can('viewAny', User::class));

        // ...and the admin only gets them because the policy says so, which is
        // also what makes the self-delete rule above enforceable.
        $this->assertTrue($admin->can('create', \App\Models\Plan::class));
        $this->assertTrue($admin->can('viewAny', User::class));
    }
}
