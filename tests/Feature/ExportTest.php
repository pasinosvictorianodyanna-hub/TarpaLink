<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payout CSV is the only way money data leaves the panel. If the download
 * silently returns an HTML page, the feature is decorative.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    public function test_the_owner_downloads_a_payout_csv(): void
    {
        $response = $this->actingAs($this->owner())->get('/shop/reports/export');

        $response->assertOk();
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Gross', $csv);
        $this->assertStringContainsString('Commission', $csv);
        $this->assertStringContainsString('Net', $csv);

        // One header row plus at least one payment for Inkwell Press.
        $this->assertGreaterThan(1, substr_count(trim($csv), "\n") + 1);
    }

    public function test_the_csv_only_contains_this_shops_payments(): void
    {
        $owner = $this->owner();
        $shop = $owner->currentShop();
        $foreign = Shop::where('slug', 'halftone-hub')->firstOrFail();

        // The export is a payout ledger, so only paid jobs appear in it.
        $paid = fn ($query) => $query->whereHas('payment', fn ($q) => $q->where('status', 'paid'));

        $ownJob = $paid($shop->jobs())->firstOrFail()->reference;
        $foreignJob = $paid($foreign->jobs())->firstOrFail()->reference;

        $csv = $this->actingAs($owner)
            ->get('/shop/reports/export')
            ->streamedContent();

        $this->assertStringContainsString($ownJob, $csv);
        $this->assertStringNotContainsString($foreignJob, $csv);
    }

    public function test_a_guest_is_redirected_rather_than_served_the_file(): void
    {
        $this->get('/shop/reports/export')->assertRedirect();
    }

    public function test_a_customer_cannot_download_a_shops_payouts(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)
            ->get('/shop/reports/export')
            ->assertForbidden();
    }

    public function test_the_reports_page_links_to_the_export(): void
    {
        $this->actingAs($this->owner())
            ->get('/shop/reports')
            ->assertOk()
            ->assertSee('/shop/reports/export');
    }
}
