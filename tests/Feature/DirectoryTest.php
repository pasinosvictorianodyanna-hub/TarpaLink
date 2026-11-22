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
            // Real coordinates now, not a 0–100 projection of the result set.
            $this->assertGreaterThan(14.0, $pin['lat']);
            $this->assertLessThan(15.0, $pin['lat']);
            $this->assertGreaterThan(120.0, $pin['lng']);
            $this->assertLessThan(122.0, $pin['lng']);
        }
    }

    /* ------------------------------------------------------------ near me */

    public function test_a_location_puts_the_nearest_shop_first(): void
    {
        // Standing on top of Inkwell Press in Diliman.
        $shops = $this->get('/shops?near=14.64000,121.05500')->assertOk()->viewData('shops');

        $this->assertSame('inkwell-press', $shops->first()->slug);
    }

    public function test_results_are_ordered_by_distance_not_by_rating(): void
    {
        $origin = ['lat' => 14.5547, 'lng' => 121.0244]; // Makati
        $shops = $this->get('/shops?near=14.55470,121.02440')->assertOk()->viewData('shops');

        $previous = -1.0;

        foreach ($shops as $shop) {
            $km = $shop->distanceKm($origin['lat'], $origin['lng']);

            $this->assertNotNull($km, "{$shop->name} has no coordinates but was returned");
            $this->assertGreaterThanOrEqual($previous, $km, "{$shop->name} is out of distance order");
            $previous = $km;
        }

        // And that order is genuinely different from the default rating order,
        // or this test would pass on a page that ignored `near` entirely.
        $byRating = $this->get('/shops')->viewData('shops');

        $this->assertNotSame($byRating->first()->slug, $shops->first()->slug);
    }

    public function test_distance_is_measured_from_the_customer_not_the_platform_origin(): void
    {
        $shops = $this->get('/shops?near=14.55470,121.02440')->assertOk()->viewData('shops');
        $shop = $shops->firstWhere('slug', 'northline-print');

        $fromCustomer = $shop->distanceKm(14.5547, 121.0244);
        $fromOrigin = $shop->distanceKm();

        $this->assertGreaterThan($fromOrigin, $fromCustomer);
    }

    public function test_the_nearest_pin_is_flagged_and_carries_a_distance(): void
    {
        $pins = $this->get('/shops?near=14.64000,121.05500')->assertOk()->viewData('pins');

        $this->assertTrue($pins[0]['nearest']);
        $this->assertSame(1, $pins[0]['n']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? (m|km)$/', $pins[0]['distance']);

        // Exactly one pin is the nearest one.
        $this->assertCount(1, array_filter($pins, fn ($pin) => $pin['nearest']));
    }

    public function test_pin_numbers_line_up_with_the_card_order(): void
    {
        $response = $this->get('/shops?near=14.64000,121.05500')->assertOk();

        $shops = $response->viewData('shops');
        $pins = $response->viewData('pins');

        foreach ($pins as $pin) {
            $this->assertSame($shops[$pin['n'] - 1]->id, $pin['id']);
        }
    }

    public function test_sorting_by_rating_keeps_the_location(): void
    {
        $response = $this->get('/shops?near=14.55470,121.02440&sort=rating')->assertOk();

        $this->assertSame('rating', $response->viewData('sort'));
        $this->assertNotNull($response->viewData('origin'));

        $ratings = $response->viewData('shops')->pluck('rating');

        $this->assertSame(
            $ratings->sortDesc()->values()->all(),
            $ratings->values()->all(),
            'sort=rating should still order by rating'
        );
    }

    public function test_a_malformed_location_is_ignored_rather_than_clamped(): void
    {
        foreach (['near=abc', 'near=14.6', 'near=999,121', 'near=14.6,999', 'near='] as $query) {
            $response = $this->get('/shops?'.$query)->assertOk();

            $this->assertNull($response->viewData('origin'), "`{$query}` should not produce an origin");
            $this->assertSame('rating', $response->viewData('sort'));
        }
    }

    public function test_the_location_survives_every_link_on_the_page(): void
    {
        $response = $this->get('/shops?near=14.64000,121.05500')->assertOk();

        // The filter rail, the search box and the service pills all have to carry
        // it, or the next click silently re-sorts from the platform origin.
        $response->assertSee('name="near" value="14.64000,121.05500"', false);
        $response->assertSee('near=14.64000%2C121.05500', false);
    }
}
