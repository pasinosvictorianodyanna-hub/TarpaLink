<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Filament\Shop\Pages\ProductionBoard;
use App\Models\User;
use App\Services\JobWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance criteria for issues #3 and #5.
 *
 * Both panels share one `web` guard, so these tests are the proof that the
 * separation actually holds — not the presence of a second guard.
 */
class ShopPanelTest extends TestCase
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

    public function test_shop_owner_can_open_the_shop_panel(): void
    {
        $this->actingAs($this->owner())
            ->get('/shop/services')
            ->assertSuccessful();
    }

    public function test_shop_owner_only_sees_their_own_catalogue(): void
    {
        $response = $this->actingAs($this->owner())->get('/shop/services');

        // Inkwell's own services are listed...
        $response->assertSee('Tarpaulin');
        $response->assertSee('Flyers');

        // ...and Halftone Hub's "Business cards" is nowhere on the page.
        $response->assertDontSee('Business cards');
    }

    public function test_shop_owner_is_forbidden_from_the_admin_panel(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_the_admin_panel_but_not_the_shop_panel(): void
    {
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertSuccessful();
        $this->actingAs($admin)->get('/shop/services')->assertForbidden();
    }

    public function test_customer_cannot_open_the_shop_panel(): void
    {
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)->get('/shop')->assertForbidden();
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

    public function test_shop_owner_can_open_the_jobs_list_and_the_board(): void
    {
        $this->actingAs($this->owner())->get('/shop/jobs')->assertSuccessful();
        $this->actingAs($this->owner())->get('/shop/production-board')->assertSuccessful();
        $this->actingAs($this->owner())->get('/shop/quantity-discounts')->assertSuccessful();
    }

    public function test_the_board_only_shows_this_shop_jobs(): void
    {
        $own = Job::where('shop_id', $this->inkwell()->id)
            ->whereIn('status', JobWorkflow::BOARD)
            ->firstOrFail();

        $other = Job::where('shop_id', '!=', $this->inkwell()->id)
            ->whereIn('status', JobWorkflow::BOARD)
            ->firstOrFail();

        $response = $this->actingAs($this->owner())->get('/shop/production-board');

        $response->assertSee($own->reference);
        $response->assertDontSee($other->reference);
    }

    /**
     * Issue #4 acceptance: edit a rate in the panel, and the storefront quotes
     * the new price with no deploy and no cache clear.
     */
    public function test_editing_a_tier_changes_the_next_quote_and_the_matrix_version(): void
    {
        $shop = $this->inkwell();
        $service = $shop->services->firstWhere('slug', 'tarpaulin');
        $tier = $service->tiers->firstWhere('label', 'Large (≤96in)');

        $calculator = app(\App\Services\PriceCalculator::class);
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

    public function test_dropping_a_card_on_the_board_moves_the_job(): void
    {
        $job = Job::where('shop_id', $this->inkwell()->id)
            ->where('status', 'quoted')
            ->firstOrFail();

        Livewire::actingAs($this->owner())
            ->test(ProductionBoard::class)
            ->call('moveJob', $job->id, 'awaiting_proof');

        $this->assertSame('awaiting_proof', $job->fresh()->status);
    }

    public function test_dropping_a_card_into_an_illegal_column_is_refused(): void
    {
        $job = Job::where('shop_id', $this->inkwell()->id)
            ->where('status', 'quoted')
            ->firstOrFail();

        Livewire::actingAs($this->owner())
            ->test(ProductionBoard::class)
            ->call('moveJob', $job->id, 'collected');

        $this->assertSame('quoted', $job->fresh()->status);
    }

    public function test_the_board_cannot_move_another_shops_job(): void
    {
        $foreign = Job::where('shop_id', '!=', $this->inkwell()->id)->firstOrFail();
        $original = $foreign->status;

        Livewire::actingAs($this->owner())
            ->test(ProductionBoard::class)
            ->call('moveJob', $foreign->id, 'ready');

        $this->assertSame($original, $foreign->fresh()->status);
    }
}
