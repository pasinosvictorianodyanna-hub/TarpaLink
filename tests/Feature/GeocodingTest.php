<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Shops\Pages\EditShop;
use App\Models\Shop;
use App\Models\User;
use App\Services\Geocoder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Address lookup.
 *
 * Shops used to get their coordinates from the seeder, which means a shop that
 * moves is unreachable and a new shop cannot be placed without a developer. The
 * interesting cases here are not the happy one — they are the ones where
 * somebody else's server is slow, down, or returns nonsense.
 */
class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // The lookup caches for a month; a leftover entry would make these tests
        // pass without ever touching the fake.
        Cache::flush();

        config([
            'services.nominatim.enabled' => true,
            'services.nominatim.url' => 'https://nominatim.test',
        ]);
    }

    private function nominatimReturns(float $lat, float $lng, string $label = 'Katipunan Ave, Quezon City'): void
    {
        Http::fake([
            'nominatim.test/*' => Http::response([[
                'lat' => (string) $lat,
                'lon' => (string) $lng,
                'display_name' => $label,
            ]]),
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    public function test_the_geocoder_returns_a_point_for_an_address(): void
    {
        $this->nominatimReturns(14.6396, 121.0778);

        $hit = app(Geocoder::class)->lookup('Katipunan Ave, Quezon City');

        $this->assertNotNull($hit);
        $this->assertSame(14.6396, $hit['lat']);
        $this->assertSame(121.0778, $hit['lng']);
        $this->assertSame('Katipunan Ave, Quezon City', $hit['label']);
    }

    public function test_the_same_address_is_only_looked_up_once(): void
    {
        $this->nominatimReturns(14.6396, 121.0778);

        app(Geocoder::class)->lookup('Katipunan Ave');
        app(Geocoder::class)->lookup('Katipunan Ave');

        // Nominatim is somebody else's server run on a volunteer budget, and its
        // usage policy is explicit about repeat queries.
        Http::assertSentCount(1);
    }

    public function test_an_unknown_address_is_not_an_error(): void
    {
        Http::fake(['nominatim.test/*' => Http::response([])]);

        $this->assertNull(app(Geocoder::class)->lookup('Nowhere at all'));
    }

    public function test_a_network_failure_is_reported_as_no_result(): void
    {
        Http::fake(['nominatim.test/*' => Http::response('gateway timeout', 504)]);

        $this->assertNull(app(Geocoder::class)->lookup('Katipunan Ave'));
    }

    public function test_an_impossible_coordinate_is_rejected(): void
    {
        // A server returning nonsense must not put a shop at latitude 500 and
        // break every distance on the page.
        $this->nominatimReturns(500.0, 900.0);

        $this->assertNull(app(Geocoder::class)->lookup('Katipunan Ave'));
    }

    public function test_the_lookup_is_skipped_entirely_when_disabled(): void
    {
        config(['services.nominatim.enabled' => false]);

        Http::fake();

        $this->assertNull(app(Geocoder::class)->lookup('Katipunan Ave'));
        Http::assertNothingSent();
    }

    public function test_an_empty_address_never_reaches_the_network(): void
    {
        Http::fake();

        $this->assertNull(app(Geocoder::class)->lookup('   '));
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- directory

    public function test_a_typed_address_positions_the_customer(): void
    {
        $this->nominatimReturns(14.6396, 121.0778);

        $this->get('/shops?address=Katipunan+Ave')
            ->assertRedirect();

        $location = urldecode((string) $this->get('/shops?address=Katipunan+Ave')->headers->get('Location'));

        $this->assertStringContainsString('near=14.6396,121.0778', $location);
        $this->assertStringContainsString('src=address', $location);
        $this->assertStringContainsString('place=Katipunan Ave', $location);
        // The address itself is dropped, so a refresh does not geocode again.
        $this->assertStringNotContainsString('address=', $location);
    }

    public function test_the_result_page_says_the_position_came_from_an_address(): void
    {
        $this->nominatimReturns(14.6396, 121.0778, 'Katipunan Avenue, Quezon City');

        $response = $this->get('/shops?near=14.6396,121.0778&src=address&place=Katipunan+Avenue');

        $response->assertOk()
            ->assertSee('the address you gave us')
            ->assertDontSee('your location');
    }

    public function test_an_address_that_cannot_be_found_says_so_and_keeps_the_list(): void
    {
        Http::fake(['nominatim.test/*' => Http::response([])]);

        $this->get('/shops?address=Nowhere+at+all')
            ->assertOk()
            ->assertSee('We could not find')
            ->assertSee('Nowhere at all')
            // The shops are still there — a failed lookup is not a failed page.
            ->assertSee('print');
    }

    public function test_the_address_box_is_hidden_when_the_lookup_is_off(): void
    {
        config(['services.nominatim.enabled' => false]);

        $this->get('/shops')
            ->assertOk()
            ->assertDontSee('Use this address');
    }

    // -------------------------------------------------------------- admin panel

    public function test_an_administrator_can_fill_a_shops_coordinates_from_its_address(): void
    {
        Filament::setCurrentPanel('admin');
        $this->nominatimReturns(14.6396, 121.0778);

        $shop = $this->shop();
        $shop->forceFill(['lat' => null, 'lng' => null])->save();

        Livewire::actingAs($this->admin())
            ->test(EditShop::class, ['record' => $shop->getRouteKey()])
            ->callAction(TestAction::make('geocodeAddress')->schemaComponent())
            ->assertHasNoActionErrors()
            ->assertNotified()
            // The action fills the form; the administrator still saves, exactly
            // as they would after typing the numbers by hand.
            ->call('save');

        $shop->refresh();

        $this->assertSame(14.6396, $shop->lat);
        $this->assertSame(121.0778, $shop->lng);
    }

    public function test_a_failed_lookup_leaves_the_existing_coordinates_alone(): void
    {
        Filament::setCurrentPanel('admin');
        Http::fake(['nominatim.test/*' => Http::response([])]);

        $shop = $this->shop();
        $shop->forceFill(['lat' => 14.6, 'lng' => 121.0])->save();

        Livewire::actingAs($this->admin())
            ->test(EditShop::class, ['record' => $shop->getRouteKey()])
            ->callAction(TestAction::make('geocodeAddress')->schemaComponent())
            ->assertNotified()
            ->call('save');

        $shop->refresh();

        // Half a coordinate is worse than none: the shop would sort as if it
        // were somewhere it is not.
        $this->assertSame(14.6, $shop->lat);
        $this->assertSame(121.0, $shop->lng);
    }
}
