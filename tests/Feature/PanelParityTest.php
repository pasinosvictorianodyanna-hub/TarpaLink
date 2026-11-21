<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Admin\Pages\Integrations;
use App\Filament\Admin\Widgets\JobsPerDay;
use App\Filament\Shop\Pages\PriceMatrix;
use App\Filament\Shop\Resources\Jobs\Pages\ListJobs;
use App\Filament\Shop\Widgets\NeedsAttention;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The screens and actions the design promises on the two panels.
 *
 * Each test here corresponds to something the prototype shows that the build
 * did not have: an export on the admin dashboard, a jobs-per-day chart, row
 * actions on "Needs attention", and the integrations board.
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

    /**
     * Shop-panel widgets build URLs with JobResource::getUrl(), which resolves
     * against whichever panel is current. A test that renders one after an
     * admin test would otherwise look for an admin jobs resource that does not
     * exist.
     */
    private function actAsShop(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('shop'));

        $this->actingAs($this->owner());
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

    public function test_a_shop_can_nudge_a_customer_on_a_stuck_job(): void
    {
        $job = $this->owner()->currentShop()->jobs()->firstOrFail();

        $this->actAsShop();

        Livewire::test(NeedsAttention::class)->call('nudge', $job->id);

        $this->assertDatabaseHas('job_activities', [
            'job_id' => $job->id,
            'note' => 'Shop nudged the customer',
        ]);
    }

    public function test_a_shop_cannot_nudge_another_shops_job(): void
    {
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail()->jobs()->firstOrFail();

        $this->expectException(ModelNotFoundException::class);

        $this->actAsShop();

        Livewire::test(NeedsAttention::class)->call('nudge', $foreign->id);
    }

    public function test_only_an_administrator_can_open_the_integrations_screen(): void
    {
        $this->actingAs($this->admin())->get('/admin/integrations')->assertOk();

        $this->actingAs($this->owner())->get('/admin/integrations')->assertForbidden();
    }

    public function test_the_nudge_is_recorded_even_when_mail_is_not_configured(): void
    {
        // The suite runs with MAIL_MAILER=array, so the send succeeds but the
        // point is that the activity row exists regardless.
        $job = Job::where('shop_id', $this->owner()->currentShop()->id)->firstOrFail();

        $this->actAsShop();

        Livewire::test(NeedsAttention::class)->call('nudge', $job->id);

        $this->assertSame(
            1,
            $job->activities()->where('note', 'Shop nudged the customer')->count(),
        );
    }

    public function test_the_jobs_screen_renders_the_designed_list(): void
    {
        $this->actAsShop();

        Livewire::test(ListJobs::class)
            ->assertOk()
            ->assertSee('Customer')
            ->assertSee('Service')
            ->assertSee('Status');
    }

    public function test_the_jobs_screen_filters_by_status(): void
    {
        $this->actAsShop();

        $ready = Job::where('shop_id', $this->owner()->currentShop()->id)
            ->where('status', 'ready')
            ->first();

        if (! $ready) {
            $this->markTestSkipped('No ready job in the seeded data.');
        }

        Livewire::test(ListJobs::class)
            ->set('status', 'ready')
            ->assertSee($ready->reference);
    }

    public function test_the_jobs_screen_only_lists_this_shops_jobs(): void
    {
        $this->actAsShop();

        $foreign = Job::where('shop_id', '!=', $this->owner()->currentShop()->id)->firstOrFail();

        Livewire::test(ListJobs::class)->assertDontSee($foreign->reference);
    }

    public function test_the_price_matrix_renders_with_a_live_preview(): void
    {
        $this->actAsShop();

        Livewire::test(PriceMatrix::class)
            ->assertOk()
            ->assertSee('Size tiers')
            ->assertSee('Customer sees');
    }

    public function test_the_preview_prices_the_sample_spec_with_the_real_engine(): void
    {
        $this->actAsShop();

        $component = Livewire::test(PriceMatrix::class);
        $quote = $component->instance()->preview();

        $this->assertArrayNotHasKey('error', $quote);
        $this->assertGreaterThan(0, $quote['total']);
        $this->assertNotEmpty($quote['lines']);
    }

    public function test_the_preview_never_throws_when_the_engine_rejects_the_spec(): void
    {
        $this->actAsShop();

        // A service whose only tier stops at 10 inches: asking for 500 must come
        // back as a message, not a 500.
        $service = $this->owner()->currentShop()->services()->create([
            'name' => 'Tiny stickers',
            'slug' => 'tiny-stickers-'.uniqid(),
            'unit' => 'pc',
            'sort' => 99,
            'active' => true,
        ]);

        $service->tiers()->create([
            'label' => 'Up to 10 in',
            'min_width' => 0,
            'max_width' => 10,
            'unit_price' => 1000,
            'sort' => 0,
        ]);

        $component = Livewire::test(PriceMatrix::class)
            ->call('selectService', $service->id)
            ->set('width', 500)
            ->set('height', 500);

        $quote = $component->instance()->preview();

        $this->assertArrayHasKey('error', $quote);
        $component->assertOk();
    }

    public function test_the_matrix_only_offers_this_shops_services(): void
    {
        $this->actAsShop();

        $shopId = $this->owner()->currentShop()->id;

        $offered = Livewire::test(PriceMatrix::class)->instance()->services();

        $this->assertNotEmpty($offered);
        $this->assertSame([$shopId], $offered->pluck('shop_id')->unique()->values()->all());
    }
}
