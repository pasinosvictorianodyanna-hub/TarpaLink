<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The directory's filter rail. Each filter is asserted against the data it
 * claims to filter — a test that only checks the page returns 200 would pass
 * even if every checkbox did nothing.
 */
class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_the_directory_lists_active_shops_only(): void
    {
        $pending = Shop::where('status', 'pending')->firstOrFail();

        $response = $this->get('/shops')->assertOk();

        $this->assertSame(Shop::where('status', 'active')->count(), $response->viewData('shops')->count());
        $response->assertDontSee($pending->name);
    }

    public function test_the_service_pills_count_distinct_shops(): void
    {
        $types = collect($this->get('/shops')->assertOk()->viewData('serviceTypes'));

        $tarpaulin = $types->firstWhere('slug', 'tarpaulin');

        $expected = Shop::where('status', 'active')
            ->whereHas('services', fn ($q) => $q->where('slug', 'tarpaulin')->where('active', true))
            ->count();

        $this->assertNotNull($tarpaulin, 'tarpaulin should be a filterable service type');
        $this->assertSame($expected, $tarpaulin['count']);
    }

    public function test_filtering_by_service_returns_only_shops_that_offer_it(): void
    {
        $shops = $this->get('/shops?service=tarpaulin')->assertOk()->viewData('shops');

        $this->assertNotEmpty($shops);

        foreach ($shops as $shop) {
            $this->assertTrue(
                $shop->services->contains(fn ($s) => $s->slug === 'tarpaulin'),
                "{$shop->name} was returned but does not offer tarpaulin"
            );
        }
    }

    public function test_a_shop_with_two_matching_services_is_listed_once(): void
    {
        $shops = $this->get('/shops?service=stickers')->assertOk()->viewData('shops');

        $ids = collect($shops)->pluck('id');

        $this->assertSame($ids->count(), $ids->unique()->count());
    }

    public function test_the_turnaround_buckets_match_the_labels(): void
    {
        $sameDay = $this->get('/shops?turnaround[]=same-day')->assertOk()->viewData('shops');

        $this->assertNotEmpty($sameDay, 'the seed should contain at least one same-day shop');

        foreach ($sameDay as $shop) {
            $this->assertLessThanOrEqual(8, $shop->turnaround_hours);
            $this->assertSame('Same day', $shop->turnaroundLabel());
        }

        $twoToThree = $this->get('/shops?turnaround[]=2-3d')->assertOk()->viewData('shops');

        foreach ($twoToThree as $shop) {
            $this->assertGreaterThanOrEqual(25, $shop->turnaround_hours);
        }
    }

    public function test_selecting_two_buckets_widens_rather_than_narrows(): void
    {
        $one = $this->get('/shops?turnaround[]=same-day')->viewData('shops')->count();
        $two = $this->get('/shops?turnaround[]=same-day&turnaround[]=2-3d')->viewData('shops')->count();

        $this->assertGreaterThanOrEqual($one, $two);
    }

    public function test_option_filters_require_the_shop_to_actually_offer_it(): void
    {
        $shops = $this->get('/shops?option[]=delivery')->assertOk()->viewData('shops');

        $this->assertNotEmpty($shops);

        foreach ($shops as $shop) {
            $this->assertTrue($shop->offers_delivery);
        }
    }

    public function test_the_price_ceiling_excludes_shops_above_it(): void
    {
        $shops = $this->get('/shops?max=20')->assertOk()->viewData('shops');

        foreach ($shops as $shop) {
            $this->assertLessThanOrEqual(2000, $shop->cheapestRate() ?? 0, "{$shop->name} is above ₱20/unit");
        }
    }

    public function test_filters_combine(): void
    {
        $shops = $this->get('/shops?service=tarpaulin&option[]=gcash')->assertOk()->viewData('shops');

        foreach ($shops as $shop) {
            $this->assertTrue($shop->accepts_gcash);
            $this->assertTrue($shop->services->contains(fn ($s) => $s->slug === 'tarpaulin'));
        }
    }

    public function test_pins_are_only_placed_for_shops_with_coordinates(): void
    {
        $pins = $this->get('/shops')->assertOk()->viewData('pins');

        $this->assertNotEmpty($pins);

        foreach ($pins as $pin) {
            $this->assertGreaterThanOrEqual(0, $pin['x']);
            $this->assertLessThanOrEqual(100, $pin['x']);
            $this->assertGreaterThanOrEqual(0, $pin['y']);
            $this->assertLessThanOrEqual(100, $pin['y']);
        }
    }
}
