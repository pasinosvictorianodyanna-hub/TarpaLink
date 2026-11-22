<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\PendingApprovals;
use App\Filament\Admin\Widgets\PlatformStats;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopApproval;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #8.
 *
 * The approval queue is not decoration: it is the thing that decides whether a
 * shop can trade and whether its owner can sign in. Both directions are tested
 * here, because "approve works" is only half a feature if "reject locks out"
 * is not true.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    private function pendingShop(): Shop
    {
        return Shop::where('status', 'pending')->firstOrFail();
    }

    private function pendingOwner(): User
    {
        return User::where('email', 'lia@meridian.ph')->firstOrFail();
    }

    public function test_the_admin_dashboard_renders_platform_figures(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Platform margin')
            ->assertSee('Active shops')
            ->assertSee('Waiting for approval');
    }

    public function test_the_live_feed_shows_the_spec_and_not_just_the_service_name(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertSuccessful()->getContent();

        // Asserted over the feed's own window rather than over one hand-picked
        // job: `Job::first()` has no order, and the feed shows eight of the
        // twelve jobs that share the newest `created_at`, so picking a job
        // separately made this pass alone and fail in the full suite.
        $feed = Job::orderByDesc('created_at')->orderByDesc('id')->limit(8)->get();

        $this->assertNotEmpty($feed);

        // A 36×48 tarpaulin and a 72×96 one are the same service and eight times
        // the money. The feed is only readable if it says which one it was.
        foreach ($feed as $job) {
            $this->assertStringContainsString(
                $job->titleLine(),
                $html,
                "The feed lost the spec for {$job->reference}.",
            );
        }
    }

    public function test_each_status_in_the_live_feed_gets_its_own_pill(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertSuccessful()->getContent();

        // The feed shows the eight newest jobs, so those are the statuses on the
        // page. One class for every status made "Ready for pickup" and "Quoted"
        // the same colour, which is the one thing the feed exists to distinguish.
        foreach (Job::orderByDesc('created_at')->orderByDesc('id')->limit(8)->get() as $job) {
            $this->assertStringContainsString(
                'tl-pill '.$job->statusTone(),
                $html,
                "No pill for {$job->reference} ({$job->status}).",
            );
        }
    }

    public function test_commission_is_the_sum_of_commissions_not_gmv_times_one_rate(): void
    {
        $expected = (int) Payment::where('status', 'paid')->sum('commission');
        $gmv = (int) Payment::where('status', 'paid')->sum('amount');

        // The two only coincide if every shop were on the same plan, which they
        // are not — so this is a real check, not a tautology.
        $this->assertNotSame((int) round($gmv * 0.05), $expected);

        Livewire::actingAs($this->admin())
            ->test(PlatformStats::class)
            ->assertSee(Money::short($expected));
    }

    public function test_the_queue_lists_the_pending_shop(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PendingApprovals::class)
            ->assertCanSeeTableRecords([$this->pendingShop()])
            ->assertSee('Meridian Press');
    }

    public function test_a_pending_owner_waits_instead_of_reaching_the_console(): void
    {
        // No active shop, so the console is closed — but a 403 told them nothing
        // about why, or whether anything had gone wrong.
        $this->actingAs($this->pendingOwner())
            ->get('/console')
            ->assertRedirect(route('console.waiting'));
    }

    public function test_approving_from_the_queue_lets_the_owner_in(): void
    {
        $shop = $this->pendingShop();

        Livewire::actingAs($this->admin())
            ->test(PendingApprovals::class)
            ->callTableAction('approve', $shop)
            ->assertHasNoTableActionErrors();

        $shop->refresh();

        $this->assertSame('active', $shop->status);
        $this->assertNotNull($shop->approved_at);
        $this->assertSame($this->admin()->id, $shop->approved_by);

        $this->actingAs(User::find($this->pendingOwner()->id))
            ->get('/console')
            ->assertSuccessful();
    }

    public function test_rejecting_locks_the_owner_out_and_keeps_the_reason(): void
    {
        $shop = $this->pendingShop();

        Livewire::actingAs($this->admin())
            ->test(PendingApprovals::class)
            ->callTableAction('reject', $shop, ['reason' => 'Business permit could not be verified.'])
            ->assertHasNoTableActionErrors();

        $shop->refresh();

        $this->assertSame('suspended', $shop->status);
        $this->assertSame('Business permit could not be verified.', $shop->status_reason);

        $this->actingAs(User::find($this->pendingOwner()->id))
            ->get('/console')
            ->assertRedirect(route('console.waiting'));
    }

    public function test_a_rejected_shop_disappears_from_the_storefront(): void
    {
        $shop = $this->pendingShop();
        $slug = $shop->slug;

        $this->actingAs($this->admin())
            ->get("/admin/shops/{$shop->slug}")
            ->assertSuccessful();

        app(ShopApproval::class)->approve($shop, $this->admin());

        $this->get("/shops/{$slug}")->assertSuccessful();

        app(ShopApproval::class)->suspend($shop->fresh(), 'Chargebacks.', $this->admin());

        $this->get("/shops/{$slug}")->assertNotFound();
    }

    public function test_a_shop_owner_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/admin')
            ->assertForbidden();
    }
}
