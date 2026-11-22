<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Shop;
use App\Services\Geocoder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    /**
     * The directory.
     *
     * Returns a redirect when `?address=` was given and could be resolved: the
     * lookup turns into a `?near=` URL, so the position is shareable and a
     * refresh does not geocode the same address again.
     */
    public function index(Request $request): View|RedirectResponse
    {
        // An address typed by the customer is looked up once and then treated
        // exactly like a GPS fix: it becomes `?near=lat,lng`. Resolving it here
        // rather than inside the map island keeps the result a shareable URL,
        // and keeps the island unaware that addresses exist at all.
        $address = trim((string) $request->query('address'));
        $geocodeError = null;

        if ($address !== '') {
            $hit = app(Geocoder::class)->lookup($address);

            if ($hit !== null) {
                return redirect()->route('shops.index', array_merge($request->query(), [
                    'near' => $hit['lat'].','.$hit['lng'],
                    'src' => 'address',
                    'place' => Str::limit($hit['label'], 90),
                    // Dropped so a refresh does not re-geocode a resolved
                    // address, and so the URL says where you are rather than
                    // what you typed to get there.
                    'address' => null,
                ]));
            }

            $geocodeError = $address;
        }

        $query = Shop::active()->with(['services.tiers', 'quantityDiscounts']);

        $search = trim((string) $request->query('q'));
        $city = trim((string) $request->query('city'));
        $service = trim((string) $request->query('service'));
        $turnaround = array_filter((array) $request->query('turnaround', []));
        $options = array_filter((array) $request->query('option', []));
        $maxPrice = $request->query('max');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('services', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($city !== '') {
            $query->where('city', 'like', "%{$city}%");
        }

        // Filtering by service is a whereHas on the shop's own catalogue, not a
        // join — a shop with two matching services must still appear once.
        if ($service !== '') {
            $query->whereHas('services', fn ($s) => $s->where('slug', $service)->where('active', true));
        }

        // Turnaround buckets, mirroring Shop::turnaroundLabel().
        $hours = collect($turnaround)->map(fn ($bucket) => match ($bucket) {
            'same-day' => [0, 8],
            '24h' => [9, 24],
            '2-3d' => [25, 999],
            default => null,
        })->filter();

        if ($hours->isNotEmpty()) {
            $query->where(function ($q) use ($hours) {
                foreach ($hours as [$from, $to]) {
                    $q->orWhereBetween('turnaround_hours', [$from, $to]);
                }
            });
        }

        foreach ($options as $option) {
            match ($option) {
                'rush' => $query->where('accepts_rush', true),
                'delivery' => $query->where('offers_delivery', true),
                'design' => $query->where('offers_design', true),
                'gcash' => $query->where('accepts_gcash', true),
                default => null,
            };
        }

        $shops = $query->orderByDesc('rating')->get();

        // The price filter is applied in PHP because "from ₱38/sq ft" is derived
        // from the cheapest tier, which is a per-shop aggregate — pushing it into
        // SQL would mean a correlated subquery that says the same thing slower.
        if ($maxPrice !== null && $maxPrice !== '') {
            $shops = $shops
                ->filter(fn (Shop $shop) => ($shop->cheapestRate() ?? 0) <= (int) $maxPrice * 100)
                ->values();
        }

        // Where the customer is standing, if they told us. Everything below —
        // the ordering, the distance on each card, the pin numbering — hangs off
        // this being non-null, so it is resolved once and passed around rather
        // than re-parsed in the view.
        $origin = $this->origin($request);

        // Default to nearest-first the moment a location exists: the whole point
        // of handing us your position is that the closest shop should be at the
        // top. Without one there is nothing to sort by, so rating wins.
        $sort = $request->query('sort') === 'rating' || $origin === null ? 'rating' : 'near';

        if ($sort === 'near') {
            $shops = $shops
                // Shops with no coordinates cannot be "nearest" to anything, so
                // they fall to the end rather than silently jumping to the top
                // (which is what a null would do in a numeric comparison).
                ->sortBy(fn (Shop $shop) => $shop->distanceKm($origin['lat'], $origin['lng']) ?? PHP_FLOAT_MAX)
                ->values();
        }

        return view('shops.index', [
            'shops' => $shops,
            'cities' => Shop::active()->distinct()->orderBy('city')->pluck('city'),
            'serviceTypes' => $this->serviceTypes(),
            'priceCeiling' => $this->priceCeiling(),
            'pins' => $this->pins($shops, $origin),
            'mapCenter' => $this->mapCenter($shops, $origin),
            'origin' => $origin,
            'sort' => $sort,
            'search' => $search,
            'city' => $city,
            'service' => $service,
            'turnaround' => $turnaround,
            'options' => $options,
            'maxPrice' => $maxPrice,
            'place' => trim((string) $request->query('place')),
            'geocodeError' => $geocodeError,
            'geocoderEnabled' => app(Geocoder::class)->enabled(),
        ]);
    }

    /**
     * The customer's position, from `?near=lat,lng`.
     *
     * Deliberately strict: a malformed or out-of-range pair is treated as "no
     * location" rather than clamped. Clamping would put the customer at a point
     * they never were, and then sort shops against a fiction.
     *
     * `src` records how the point was obtained — `gps` from the browser's
     * geolocation API, `pin` from a tap on the map — so the caption can say
     * "your location" or "the pin you dropped" instead of guessing.
     *
     * @return array{lat: float, lng: float, src: string}|null
     */
    private function origin(Request $request): ?array
    {
        $near = trim((string) $request->query('near'));

        if ($near === '' || ! str_contains($near, ',')) {
            return null;
        }

        [$lat, $lng] = array_map('trim', explode(',', $near, 2));

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            // `address` is a position the customer typed and we looked up. It is
            // not a GPS fix and must not be described as one — "the address you
            // gave us" is the honest caption.
            'src' => in_array($request->query('src'), ['pin', 'address'], true)
                ? (string) $request->query('src')
                : 'gps',
        ];
    }

    /**
     * Every service type on the platform with how many active shops offer it.
     * The counts are what make the rail useful — they tell you, before you
     * click, whether a filter is worth applying.
     *
     * @return array<int, array{slug: string, name: string, count: int}>
     */
    private function serviceTypes(): array
    {
        return Service::query()
            ->where('active', true)
            ->whereHas('shop', fn ($q) => $q->where('status', 'active'))
            ->selectRaw('slug, min(name) as name, count(distinct shop_id) as shops')
            ->groupBy('slug')
            ->orderByDesc('shops')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'count' => (int) $row->shops,
            ])
            ->all();
    }

    /** The most expensive "from" price on the platform, in pesos, rounded up. */
    private function priceCeiling(): int
    {
        $highest = Shop::active()->with('services.tiers')->get()
            ->map(fn (Shop $shop) => $shop->cheapestRate() ?? 0)
            ->max() ?? 0;

        return max(10, (int) ceil($highest / 100 / 10) * 10);
    }

    /**
     * The map's pins: real coordinates, numbered to match the cards below.
     *
     * The previous version normalised lat/lng into a 0–100 box using the
     * min/max of the *current result set*, which meant a shop's position on the
     * map changed every time a filter changed — a pin's location was relative to
     * whatever else happened to be on screen, not to the city. The numbers are
     * now handed to Leaflet as latitude/longitude, so position means position.
     *
     * Numbering runs off the card index, not the pin index, so a shop with no
     * coordinates leaves a gap instead of shifting every later number by one and
     * quietly breaking the pin-to-card correspondence.
     *
     * @param  \Illuminate\Support\Collection<int, Shop>  $shops
     * @param  array{lat: float, lng: float, src: string}|null  $origin
     * @return array<int, array<string, mixed>>
     */
    private function pins($shops, ?array $origin): array
    {
        // "Nearest" is a fact about the geometry, not about the sort order — if
        // the customer flips the list back to rating order the closest shop is
        // still the closest shop, and the map should still say so.
        $nearestId = $origin === null
            ? null
            : $shops
                ->filter(fn (Shop $shop) => $shop->lat !== null && $shop->lng !== null)
                ->sortBy(fn (Shop $shop) => $shop->distanceKm($origin['lat'], $origin['lng']) ?? PHP_FLOAT_MAX)
                ->first()?->id;

        $pins = [];

        foreach ($shops->values() as $i => $shop) {
            if ($shop->lat === null || $shop->lng === null) {
                continue;
            }

            $pins[] = [
                'n' => $i + 1,
                'id' => $shop->id,
                'name' => $shop->name,
                'city' => $shop->city,
                'url' => route('shops.show', $shop),
                'lat' => $shop->lat,
                'lng' => $shop->lng,
                'distance' => $origin === null
                    ? null
                    : $shop->distanceLabelShort($origin['lat'], $origin['lng']),
                'nearest' => $shop->id === $nearestId,
                'price' => '₱'.number_format(($shop->cheapestRate() ?? 0) / 100),
                'unit' => $shop->cheapestUnitLabel(),
                'rating' => number_format($shop->rating, 1),
                'turnaround' => $shop->turnaroundLabel(),
            ];
        }

        return $pins;
    }

    /**
     * Where the map opens.
     *
     * With a location, the answer is trivially the location. Without one, the
     * centre of the shop bounding box — not the platform origin — because the
     * thing on screen should be the shops, and Metro Manila is wide enough that
     * anchoring on Quezon City puts Caloocan off the top of the panel.
     *
     * @param  \Illuminate\Support\Collection<int, Shop>  $shops
     * @param  array{lat: float, lng: float, src: string}|null  $origin
     * @return array{lat: float, lng: float}
     */
    private function mapCenter($shops, ?array $origin): array
    {
        if ($origin !== null) {
            return ['lat' => $origin['lat'], 'lng' => $origin['lng']];
        }

        $located = $shops->filter(fn (Shop $shop) => $shop->lat !== null && $shop->lng !== null);

        if ($located->isEmpty()) {
            // Metro Manila's rough centre, used only when nothing is mappable.
            return ['lat' => 14.6091, 'lng' => 121.0223];
        }

        return [
            'lat' => round($located->avg('lat'), 5),
            'lng' => round($located->avg('lng'), 5),
        ];
    }

    public function show(Shop $shop): View
    {
        abort_unless($shop->status === 'active', 404);

        $shop->load(['services.tiers', 'services.options']);

        return view('shops.show', [
            'shop' => $shop,
            'services' => $shop->services,
        ]);
    }

    public function builder(Request $request, Shop $shop): View
    {
        abort_unless($shop->status === 'active', 404);

        $shop->load(['services.tiers', 'services.options']);

        abort_if($shop->services->isEmpty(), 404);

        // `?service_id=` comes from "Reorder" and "Edit" on a past job, so the
        // builder opens on what the customer had rather than the shop's first
        // service at the default size.
        $wanted = (int) $request->query('service_id');

        return view('shops.builder', [
            'shop' => $shop,
            'payload' => [
                'initialServiceId' => $shop->services->firstWhere('id', $wanted)?->id,
                'initialWidth' => $request->query('width_in') !== null ? (float) $request->query('width_in') : null,
                'initialHeight' => $request->query('height_in') !== null ? (float) $request->query('height_in') : null,
                'initialQty' => $request->query('qty') !== null ? (int) $request->query('qty') : null,
                'shop' => [
                    'slug' => $shop->slug,
                    'name' => $shop->name,
                    'city' => $shop->city,
                    'checkout_url' => route('checkout.show', $shop),
                ],
                'services' => $shop->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'unit' => $service->unit,
                    'blurb' => $service->blurb,
                    // Cheapest tier, so the card can say "from ₱45 / sq ft" the way
                    // the directory does. The exact price still comes from /api/quote.
                    'from' => $service->tiers->min('unit_price'),
                    'tiers' => $service->tiers->sortBy('sort')->map(fn ($t) => [
                        'id' => $t->id,
                        'label' => $t->label,
                        'min_width' => (float) $t->min_width,
                        'max_width' => $t->max_width !== null ? (float) $t->max_width : null,
                        'unit_price' => $t->unit_price,
                    ])->values(),
                    'options' => $service->options->map(fn ($o) => [
                        'id' => $o->id,
                        'name' => $o->name,
                        'unit' => $o->unit,
                        'price' => $o->price,
                    ])->values(),
                ])->values(),
                'quoteUrl' => route('quote'),
            ],
        ]);
    }
}
