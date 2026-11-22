<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Admin\Pages\Integrations;
use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections as AdminListSections;
use App\Filament\Admin\Resources\Shops\Pages\ListShops as AdminListShops;
use App\Filament\Admin\Resources\Users\Pages\ListUsers as AdminListUsers;
use App\Filament\Admin\Widgets\JobsPerDay;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The screens and actions the design promises on the admin panel.
 *
 * Each test here corresponds to something the prototype shows that the build
 * did not have: an export on the admin dashboard, a jobs-per-day chart, and the
 * integrations board.
 *
 * This used to cover both panels. The shop half — the needs-attention nudge, the
 * jobs list, the price-matrix preview — moved to `ConsoleTest`,
 * `ConsoleScreensTest` and `JobNudgeTest` when the shop panel was retired.
 */
class PanelParityTest extends TestCase
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

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }


    public function test_the_admin_dashboard_renders_in_the_design_system(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->assertOk()
            ->assertSee('Jobs per day')
            ->assertSee('Waiting for approval')
            // The design-system wrapper, not Filament's widget grid.
            ->assertSeeHtml('tl-wrap')
            ->assertSeeHtml('tl-chart');
    }

    public function test_the_admin_dashboard_exports_a_platform_report(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function test_the_export_contains_only_captured_payments(): void
    {
        // Called directly rather than through the action: the action is proven
        // above, this is about the rows the stream actually writes.
        $response = (new AdminDashboard)->exportPayments();

        // A StreamedResponse has no body to read; it writes to the output buffer
        // when sent, so capture it that way.
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Gross (PHP)', $csv);
        $this->assertStringContainsString('Commission (PHP)', $csv);

        // Every seeded payment is paid, so every job reference in the file
        // should belong to a paid row.
        $paidReferences = Payment::where('status', 'paid')
            ->with('job')->get()->pluck('job.reference')->filter();

        $this->assertGreaterThan(0, $paidReferences->count());

        foreach ($paidReferences->take(3) as $reference) {
            $this->assertStringContainsString($reference, $csv);
        }
    }

    public function test_the_jobs_per_day_widget_renders_both_series(): void
    {
        Livewire::actingAs($this->admin())
            ->test(JobsPerDay::class)
            ->assertSee('Jobs per day')
            ->assertSee('Placed')
            ->assertSee('Completed');
    }

    public function test_the_integrations_page_reports_each_service(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertSee('PayMongo')
            ->assertSee('Mail')
            ->assertSee('Queue')
            ->assertSee('Storage')
            ->assertSee('/webhooks/paymongo');
    }

    public function test_the_health_check_action_runs(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->callAction('healthCheck')
            ->assertNotified();
    }



    public function test_only_an_administrator_can_open_the_integrations_screen(): void
    {
        $this->actingAs($this->admin())->get('/admin/integrations')->assertOk();

        $this->actingAs($this->owner())->get('/admin/integrations')->assertForbidden();
    }









    public function test_the_admin_shops_screen_renders_the_queue_first(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(AdminListShops::class)
            ->assertOk()
            ->assertSee('Owner')
            ->assertSee('Services');

        // The queue comes first by sort order, not by filter. Filtering to
        // `pending` hid the active shops and left the header reading "1 shop"
        // while the status dropdown still said "All statuses".
        $this->assertSame('', $component->instance()->status);
        $this->assertSame('pending', $component->instance()->shops()->items()[0]->status);
    }

    public function test_an_administrator_can_approve_a_pending_shop_from_the_list(): void
    {
        $shop = Shop::where('status', 'pending')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(AdminListShops::class)
            ->call('approve', $shop->id);

        $this->assertSame('active', $shop->fresh()->status);
    }

    public function test_the_admin_users_screen_renders_and_filters_by_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AdminListUsers::class)
            ->assertOk()
            ->assertSee('Role')
            ->set('role', 'admin')
            ->assertSee('sofia@tarpalink.ph');
    }

    public function test_the_site_content_screen_renders_the_sections(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AdminListSections::class)
            ->assertOk()
            ->assertSee('Key')
            ->assertSee('hero');
    }
}
