<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $query = Shop::active()->with(['services.tiers']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('services', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($city = trim((string) $request->query('city'))) {
            $query->where('city', 'like', "%{$city}%");
        }

        $shops = $query->orderByDesc('rating')->get();
        $cities = Shop::active()->distinct()->orderBy('city')->pluck('city');

        return view('shops.index', [
            'shops' => $shops,
            'cities' => $cities,
            'search' => $search ?? '',
            'city' => $city ?? '',
        ]);
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
