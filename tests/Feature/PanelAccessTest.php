<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\LatestJobs;
use App\Filament\Admin\Widgets\PendingApprovals;
use App\Filament\Admin\Widgets\PlatformStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel access is the security boundary in docs/ISSUES.md #3 and #9:
 * one guard, one users table, access decided in canAccessPanel().
 */
class PanelAccessTest extends TestCase
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

    public function test_admin_can_open_the_admin_panel(): void
    {
        $this->actingAs($this->user('sofia@tarpalink.ph'))
            ->get('/admin')
            ->assertOk();
    }

    public function test_shop_owner_cannot_open_the_admin_panel(): void
    {
        $this->actingAs($this->user('kevin@inkwell.ph'))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_shop_owner_can_open_the_shop_panel(): void
    {
        $this->actingAs($this->user('kevin@inkwell.ph'))
            ->get('/shop')
            ->assertOk();
    }

    public function test_customer_cannot_open_the_shop_panel(): void
    {
        $this->actingAs($this->user('jomar@tarpalink.ph'))
            ->get('/shop')
            ->assertForbidden();
    }

    public function test_shop_owner_with_a_suspended_shop_is_locked_out(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $owner->activeShop()->update(['status' => 'suspended']);

        $this->actingAs($owner->fresh())
            ->get('/shop')
            ->assertForbidden();
    }

    public function test_payouts_page_renders_for_the_owner(): void
    {
        $owner = $this->user('kevin@inkwell.ph');
        $payout = $owner->activeShop()->payouts()->firstOrFail();

        $this->actingAs($owner)
            ->get('/shop/payouts')
            ->assertOk()
            ->assertSee('Payouts')
            ->assertSee('Gross')
            // The ledger shows real money, formatted once at the edge.
            ->assertSee(\App\Support\Money::format($payout->net));
    }

    public function test_reports_page_renders_for_the_owner(): void
    {
        $this->actingAs($this->user('kevin@inkwell.ph'))
            ->get('/shop/reports')
            ->assertOk()
            ->assertSee('Revenue by week')
            ->assertSee('Top services by revenue')
            ->assertSee('Tarpaulin');
    }

    public function test_billing_page_renders_for_the_owner(): void
    {
        $this->actingAs($this->user('kevin@inkwell.ph'))
            ->get('/shop/billing')
            ->assertOk()
            ->assertSee('Growth')
            ->assertSee('Current plan')
            ->assertSee('Invoices');
    }

    public function test_admin_dashboard_loads_and_widgets_carry_the_real_numbers(): void
    {
        $admin = $this->user('sofia@tarpalink.ph');

        // Widgets are lazy-loaded, so the dashboard shell and the widget bodies
        // are asserted separately.
        $this->actingAs($admin)->get('/admin')->assertOk();

        Livewire::test(PendingApprovals::class)
            ->assertSee('Karatula Print')
            ->assertSee('Approve');

        Livewire::test(PlatformStats::class)
            ->assertSee('Commission earned')
            ->assertSee('Active shops');

        Livewire::test(LatestJobs::class)
            ->assertSee('Latest jobs');
    }

    public function test_admin_can_open_cms_and_billing_resources(): void
    {
        $admin = $this->user('sofia@tarpalink.ph');

        $this->actingAs($admin)->get('/admin/page-sections')->assertOk();
        $this->actingAs($admin)->get('/admin/plans')->assertOk();
        $this->actingAs($admin)->get('/admin/invoices')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/shops')->assertOk();
    }
}
