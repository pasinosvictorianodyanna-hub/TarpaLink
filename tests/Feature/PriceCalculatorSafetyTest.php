<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The price is decided in one place, so its invariants have to hold there —
 * including for data that never passed through the Filament form that normally
 * validates it.
 */
class PriceCalculatorSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_an_impossible_discount_cannot_make_a_job_cost_less_than_zero(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();
        $service = $shop->services()->firstOrFail();

        // The form caps this at 90%, but a seeded/imported row does not have to
        // come from the form. FR-33 says the total is never negative.
        $shop->quantityDiscounts()->create(['min_qty' => 1, 'percent' => 150]);

        $quote = app(PriceCalculator::class)->quote($shop, [
            'service_id' => $service->id,
            'width_in' => 36,
            'height_in' => 48,
            'qty' => 1,
        ]);

        $this->assertSame($quote['subtotal'], $quote['discount']);
        $this->assertSame(0, $quote['total']);
        $this->assertGreaterThanOrEqual(0, $quote['total']);
    }
}
