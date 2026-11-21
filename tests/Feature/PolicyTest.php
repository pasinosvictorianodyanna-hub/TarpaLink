<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\PageSection;
use App\Models\PriceTier;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * docs/ISSUES.md #9: four roles, policies on every model.
 */
class PolicyTest extends TestCase
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

    public function test_shop_owner_can_edit_their_own_price_matrix(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $tier = $owner->activeShop()->services()->first()->tiers()->first();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $tier));
    }

    public function test_shop_owner_cannot_edit_another_shops_price_matrix(): void
    {
        $owner = $this->user('kevin@inkwell.ph');

        $otherShop = Shop::where('slug', 'halftone-hub')->firstOrFail();
        $foreignTier = $otherShop->services()->first()->tiers()->first();

        $this->assertFalse(Gate::forUser($owner)->allows('update', $foreignTier));
    }

    public function test_customer_cannot_touch_a_price_matrix(): void
    {
        $customer = $this->user('jomar@tarpalink.ph');
        $tier = PriceTier::firstOrFail();

        $this->assertFalse(Gate::forUser($customer)->allows('update', $tier));
        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', PriceTier::class));
    }

    public function test_only_an_administrator_may_edit_site_content(): void
    {
        $section = PageSection::where('key', 'hero')->firstOrFail();

        $this->assertTrue(Gate::forUser($this->user('sofia@tarpalink.ph'))->allows('update', $section));
        $this->assertFalse(Gate::forUser($this->user('kevin@inkwell.ph'))->allows('update', $section));
        $this->assertFalse(Gate::forUser($this->user('jomar@tarpalink.ph'))->allows('update', $section));
    }

    public function test_a_customer_sees_their_own_job_but_not_someone_elses(): void
    {
        $jomar = $this->user('jomar@tarpalink.ph');
        $marisol = $this->user('marisol@example.com');

        $own = Job::where('user_id', $jomar->id)->firstOrFail();
        $theirs = Job::where('user_id', $marisol->id)->firstOrFail();

        $this->assertTrue(Gate::forUser($jomar)->allows('view', $own));
        $this->assertFalse(Gate::forUser($jomar)->allows('view', $theirs));
    }

    public function test_a_shop_can_view_its_own_jobs_only(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $shop = $owner->activeShop();

        $own = $shop->jobs()->firstOrFail();
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail()->jobs()->firstOrFail();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $own));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $foreign));
    }

    public function test_only_an_administrator_can_approve_a_shop(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $this->assertTrue(Gate::forUser($this->user('sofia@tarpalink.ph'))->allows('approve', $shop));
        $this->assertFalse(Gate::forUser($this->user('kevin@inkwell.ph'))->allows('approve', $shop));
    }

    public function test_an_administrator_cannot_delete_themselves(): void
    {
        $admin = $this->user('sofia@tarpalink.ph');

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }
}
