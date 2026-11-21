<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
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

        return view('shops.index', [
            'shops' => $shops,
            'cities' => Shop::active()->distinct()->orderBy('city')->pluck('city'),
            'serviceTypes' => $this->serviceTypes(),
            'priceCeiling' => $this->priceCeiling(),
            'pins' => $this->pins($shops),
            'search' => $search,
            'city' => $city,
            'service' => $service,
            'turnaround' => $turnaround,
            'options' => $options,
            'maxPrice' => $maxPrice,
        ]);
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
     * Pin positions for the map panel, normalised from lat/lng into a 0–100 box.
     * With no tile server this is a honest scatter of where the shops actually
     * are, not a decorative illustration.
     *
     * @param  \Illuminate\Support\Collection<int, Shop>  $shops
     * @return array<int, array{x: float, y: float, name: string, city: string, url: string}>
     */
    private function pins($shops): array
    {
        $located = $shops->filter(fn (Shop $shop) => $shop->lat && $shop->lng);

        if ($located->isEmpty()) {
            return [];
        }

        $lats = $located->pluck('lat');
        $lngs = $located->pluck('lng');
        $latSpan = max(0.0001, $lats->max() - $lats->min());
        $lngSpan = max(0.0001, $lngs->max() - $lngs->min());

        return $located->values()->map(fn (Shop $shop, int $i) => [
            'x' => 10 + (($shop->lng - $lngs->min()) / $lngSpan) * 80,
            // Kept above 78% so no pin lands on the caption pill in the corner.
            'y' => 76 - (($shop->lat - $lats->min()) / $latSpan) * 62,
            'name' => $shop->name,
            'city' => $shop->city,
            'url' => route('shops.show', $shop),
            'n' => $i + 1,
        ])->all();
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

    public function builder(Shop $shop): View
    {
        abort_unless($shop->status === 'active', 404);

        $shop->load(['services.tiers', 'services.options']);

        abort_if($shop->services->isEmpty(), 404);

        return view('shops.builder', [
            'shop' => $shop,
            'payload' => [
                'shop' => [
                    'slug' => $shop->slug,
                    'name' => $shop->name,
                    'city' => $shop->city,
                    'checkout_url' => route('checkout.show', $shop),
                ],
                'services' => $shop->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'unit' => $service->unit,
                    'blurb' => $service->blurb,
                    // Cheapest tier, so the card can say "from ₱45 / sq ft" the way
                    // the directory does. The exact price still comes from /api/quote.
                    'from' => $service->tiers->min('unit_price'),
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
