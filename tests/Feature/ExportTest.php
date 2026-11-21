<?php

namespace Tests\Feature;

use App\Filament\Shop\Pages\Payouts as PayoutsPage;
use App\Filament\Shop\Pages\Reports as ReportsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The CSV exports are the only way money data leaves the panel. If the download
 * silently does nothing, the feature is decorative.
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

    public function test_the_payouts_ledger_downloads_as_csv(): void
    {
        Livewire::actingAs($this->owner())
            ->test(PayoutsPage::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function test_the_weekly_revenue_report_downloads_as_csv(): void
    {
        Livewire::actingAs($this->owner())
            ->test(ReportsPage::class)
            ->callAction('exportWeeks')
            ->assertFileDownloaded();
    }

    public function test_the_service_revenue_report_downloads_as_csv(): void
    {
        Livewire::actingAs($this->owner())
            ->test(ReportsPage::class)
            ->callAction('exportServices')
            ->assertFileDownloaded();
    }
}
