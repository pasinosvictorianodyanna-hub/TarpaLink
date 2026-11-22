<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use App\Services\JobWorkflow;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The rules a job moves by, independent of any screen.
 *
 * This file used to be `ShopPanelTest` and was mostly about the Filament panel:
 * which role could open which URL, and Livewire calls on the board widget. Those
 * screens are gone — the console has its own coverage in `ConsoleTest` and
 * `ConsoleScreensTest` — but the rules underneath them were never about the
 * panel, and they are what makes the board and the job screen agree.
 *
 * Deliberately no HTTP here. A transition that is illegal is illegal whether it
 * arrives from the board, the job screen or a future API, and testing it through
 * one of those would only prove that one of them asks.
 */
class ShopWorkflowTest extends TestCase
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

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    public function test_a_job_cannot_skip_from_quoted_to_collected(): void
    {
        $job = Job::where('status', 'quoted')->firstOrFail();
        $before = $job->status;

        try {
            JobWorkflow::transition($job, 'collected', $this->owner());
            $this->fail('The transition should have been rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // The row must be untouched.
        $this->assertSame($before, $job->fresh()->status);
        $this->assertSame(0, $job->activities()->count());
    }

    public function test_a_legal_transition_is_recorded(): void
    {
        $job = Job::where('status', 'quoted')->firstOrFail();

        JobWorkflow::transition($job, 'awaiting_proof', $this->owner());

        $this->assertSame('awaiting_proof', $job->fresh()->status);
        $this->assertDatabaseHas('job_activities', [
            'job_id' => $job->id,
            'from_status' => 'quoted',
            'to_status' => 'awaiting_proof',
        ]);
    }

    public function test_cancelling_is_blocked_once_a_job_is_on_the_press(): void
    {
        $job = Job::where('status', 'in_production')->firstOrFail();

        $this->assertFalse(JobWorkflow::canTransition($job->status, 'cancelled'));
        $this->assertTrue(JobWorkflow::canTransition('approved', 'cancelled'));
    }

    /**
     * Issue #4 acceptance: edit a rate, and the storefront quotes the new price
     * with no deploy and no cache clear.
     */
    public function test_editing_a_tier_changes_the_next_quote_and_the_matrix_version(): void
    {
        $shop = $this->inkwell();
        $service = $shop->services->firstWhere('slug', 'tarpaulin');
        $tier = $service->tiers->firstWhere('label', 'Large (≤96in)');

        $calculator = app(PriceCalculator::class);
        $spec = [
            'service_id' => $service->id,
            'width_in' => 48,
            'height_in' => 96,
            'qty' => 1,
            'options' => [],
        ];

        $before = $calculator->quote($shop->fresh(), $spec);

        // Raise the rate by ₱1.00 per square foot (100 centavos).
        $tier->update(['unit_price' => $tier->unit_price + 100]);

        $after = $calculator->quote($shop->fresh(), $spec);

        // 48 × 96 in = 32 sq ft, so ₱1/sq ft is exactly ₱32 more.
        $this->assertSame($before['total'] + 3200, $after['total']);
        $this->assertNotSame($before['matrix_version'], $after['matrix_version']);
    }
}
