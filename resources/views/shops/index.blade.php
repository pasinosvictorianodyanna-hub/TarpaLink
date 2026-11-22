@extends('layouts.app')

@section('title', 'Print shops — TarpaLink')

@php
    $filtersActive = $city !== '' || $service !== '' || ! empty($turnaround) || ! empty($options) || ($maxPrice !== null && $maxPrice !== '');
    $originLat = $origin['lat'] ?? null;
    $originLng = $origin['lng'] ?? null;

    // Everything the page currently filters by, in one array, so no link can
    // silently drop a checkbox the customer ticked. Clicking a service pill used
    // to reset the turnaround and options filters for exactly this reason.
    $state = array_filter([
        'q' => $search,
        'city' => $city,
        'service' => $service,
        'max' => $maxPrice,
        'turnaround' => array_values($turnaround),
        'option' => array_values($options),
    ]);

    // The customer's position has to survive every link and form on this page.
    // Lose it and the next click teleports them back to the platform origin —
    // the list re-sorts, the pins move, and nothing explains why.
    $near = $origin
        ? number_format($originLat, 5, '.', '').','.number_format($originLng, 5, '.', '')
        : null;

    $keep = array_filter(['near' => $near, 'src' => $origin['src'] ?? null]);
    $byNear = array_merge($state, $keep);
    $byRating = array_merge($state, $keep, ['sort' => 'rating']);
@endphp

@section('content')
<div class="app">
    <div class="app-body">
        <div class="crumbs">Home <span>/</span> Print shops</div>

        <div class="page-head">
            <div>
                <h1 class="h3">{{ $shops->count() }} {{ Str::plural('print shop', $shops->count()) }}</h1>
                <p>
                    @if ($origin)
                        Closest first, measured from {{ $origin['src'] === 'pin' ? 'the pin you dropped' : 'your location' }}.
                    @else
                        Every shop publishes its own price matrix, so the number you see is the number you pay.
                    @endif
                </p>
            </div>
            <form method="GET" action="{{ route('shops.index') }}" class="dir-search">
                @foreach (array_merge(array_diff_key($state, ['q' => 1]), $keep) as $key => $value)
                    @foreach ((array) $value as $item)
                        <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $item }}">
                    @endforeach
                @endforeach
                <input type="search" name="q" value="{{ $search }}" placeholder="Shop name or area…" aria-label="Search print shops by name or area">
                <button class="btn btn-primary btn-sm" type="submit">Search</button>
            </form>
        </div>

        {{-- Category pills: the fastest filter, so it sits above everything else. --}}
        <div class="dir-chips">
            <a class="chip-btn {{ $service === '' ? 'on' : '' }}"
               href="{{ route('shops.index', array_merge(array_diff_key($state, ['service' => 1]), $keep)) }}">All services</a>
            @foreach ($serviceTypes as $type)
                <a class="chip-btn {{ $service === $type['slug'] ? 'on' : '' }}"
                   href="{{ route('shops.index', array_merge($state, $keep, ['service' => $type['slug']])) }}">
                    {{ $type['name'] }} <span class="chip-n">{{ $type['count'] }}</span>
                </a>
            @endforeach
        </div>

        @if ($origin)
            {{-- Ordering is a real choice once a location exists: nearest is the
                 useful default, but a customer who came for a specific shop's
                 rating should be able to get back to it without clearing their
                 position. --}}
            <div class="dir-sort">
                <span>Sort</span>
                <a class="{{ $sort === 'near' ? 'on' : '' }}" href="{{ route('shops.index', $byNear) }}">Nearest</a>
                <a class="{{ $sort === 'rating' ? 'on' : '' }}" href="{{ route('shops.index', $byRating) }}">Top rated</a>
                <a class="dir-sort-clear" href="{{ route('shops.index', $state) }}">Forget my location</a>
            </div>
        @endif

        <div class="dir-grid">
            <aside class="dir-rail">
                <form method="GET" action="{{ route('shops.index') }}" id="dir-filters">
                    {{-- `city` is not duplicated here: the select below is the
                         authoritative input, and a hidden copy would fight it. --}}
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="service" value="{{ $service }}">
                    @foreach ($keep as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    @if ($sort === 'rating')
                        <input type="hidden" name="sort" value="rating">
                    @endif

                    <div class="rail-head">
                        <span>Filters</span>
                        @if ($filtersActive)
                            <a href="{{ route('shops.index', $keep) }}">Clear</a>
                        @endif
                    </div>

                    <div class="rail-group">
                        <h5><label for="dir-city-select">City</label></h5>
                        <select id="dir-city-select" name="city" class="rail-select" onchange="this.form.submit()">
                            <option value="">All cities</option>
                            @foreach ($cities as $option)
                                <option value="{{ $option }}" @selected($city === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rail-group">
                        <h5><label for="dir-max-range">Max price</label> <span class="rail-note" id="dir-max-label">up to ₱{{ $maxPrice ?: $priceCeiling }} / unit</span></h5>
                        <input id="dir-max-range" type="range" name="max" min="10" max="{{ $priceCeiling }}" step="5"
                               value="{{ $maxPrice ?: $priceCeiling }}" class="rail-range"
                               aria-label="Maximum base price per unit"
                               oninput="document.getElementById('dir-max-label').textContent = 'up to ₱' + this.value + ' / unit'"
                               onchange="this.form.submit()">
                        <div class="rail-scale"><span>₱10</span><span>₱{{ $priceCeiling }}</span></div>
                    </div>

                    <div class="rail-group">
                        <h5>Turnaround</h5>
                        <label class="rail-check">
                            <input type="checkbox" name="turnaround[]" value="same-day" @checked(in_array('same-day', $turnaround))>
                            <span>Same day</span>
                        </label>
                        <label class="rail-check">
                            <input type="checkbox" name="turnaround[]" value="24h" @checked(in_array('24h', $turnaround))>
                            <span>24 hours</span>
                        </label>
                        <label class="rail-check">
                            <input type="checkbox" name="turnaround[]" value="2-3d" @checked(in_array('2-3d', $turnaround))>
                            <span>2–3 days</span>
                        </label>
                    </div>

                    <div class="rail-group">
                        <h5>Options</h5>
                        <label class="rail-check">
                            <input type="checkbox" name="option[]" value="rush" @checked(in_array('rush', $options))>
                            <span>Rush accepted</span>
                        </label>
                        <label class="rail-check">
                            <input type="checkbox" name="option[]" value="delivery" @checked(in_array('delivery', $options))>
                            <span>Delivery available</span>
                        </label>
                        <label class="rail-check">
                            <input type="checkbox" name="option[]" value="design" @checked(in_array('design', $options))>
                            <span>Design assistance</span>
                        </label>
                        <label class="rail-check">
                            <input type="checkbox" name="option[]" value="gcash" @checked(in_array('gcash', $options))>
                            <span>Accepts GCash</span>
                        </label>
                    </div>

                    <button class="btn btn-primary btn-sm rail-apply" type="submit">Apply filters</button>
                </form>
            </aside>

            <div class="dir-main">
                @if (! empty($pins))
                    {{-- A real map, not a scatter: the pins are latitude/longitude
                         handed to Leaflet, so a shop sits where the shop is. The
                         island owns panning and the location fix; the ordering
                         stays on the server, because a list sorted in the browser
                         is a list nobody can share. --}}
                    <div id="shop-map" class="dir-map-slot"
                         data-component="shop-map"
                         data-props="{{ json_encode([
                             'pins' => $pins,
                             'origin' => $origin,
                             'center' => $mapCenter,
                             'zoom' => $origin ? 12 : 11,
                         ], JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                        <div class="dir-map dir-map--loading">
                            <span>Loading the map…</span>
                        </div>
                    </div>
                @endif

                @if ($shops->isEmpty())
                    <div class="card" style="padding:48px 24px;text-align:center">
                        <b>No shops match those filters.</b>
                        <p class="small" style="margin-top:6px">Try widening the price range or clearing a filter.</p>
                        <a class="btn btn-ghost btn-sm" style="margin-top:16px" href="{{ route('shops.index', $keep) }}">Clear filters</a>
                    </div>
                @else
                    <div class="dir-results">
                        @foreach ($shops as $shop)
                            @php
                                $isNearest = $origin && $loop->first;
                                $km = $origin ? $shop->distanceLabelShort($originLat, $originLng) : null;
                            @endphp
                            <article class="shop">
                                <a href="{{ route('shops.show', $shop) }}" class="shop-cover" style="background:{{ $shop->coverGradient() }}">
                                    <div class="dots halftone"></div><div class="crop"></div>
                                    {{-- The number is the pin number. It is the only
                                         thing tying a card to its dot on the map. --}}
                                    <span class="shop-rank">{{ $loop->iteration }}</span>
                                    <span class="label">{{ $shop->name }}</span>
                                </a>
                                <div class="shop-body">
                                    <div class="shop-name">
                                        <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a>
                                        <svg class="vf" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 2.4 3.3-.6.6 3.3L21 9.5 19.6 12 21 14.5l-2.7 2.4-.6 3.3-3.3-.6L12 22l-2.4-2.4-3.3.6-.6-3.3L3 14.5 4.4 12 3 9.5l2.7-2.4.6-3.3 3.3.6z" opacity=".9"/></svg>
                                    </div>
                                    <div class="shop-meta">
                                        <span class="st"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>{{ number_format($shop->rating, 1) }}</span>
                                        <span>({{ $shop->jobs_done }})</span><span>·</span>
                                        {{-- City is the distance when there is no distance: showing
                                             both wraps the row onto two lines and makes the cards
                                             different heights for no information gained. --}}
                                        @if ($km)
                                            <span class="shop-dist">{{ $km }} away</span><span>·</span>
                                        @else
                                            <span>{{ $shop->city }}</span><span>·</span>
                                        @endif
                                        <span>{{ $shop->turnaroundLabel() }}</span>
                                    </div>
                                    <p class="shop-desc">{{ $shop->description }}</p>

                                    <div class="shop-badges">
                                        @if ($isNearest)
                                            <span class="tag tag-near">Nearest to you</span>
                                        @endif
                                        @foreach ($shop->badges() as $badge)
                                            <span class="tag tag-{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                        @endforeach
                                    </div>

                                    <div class="shop-foot">
                                        <span class="shop-price">from <b>₱{{ number_format(($shop->cheapestRate() ?? 0) / 100) }}</b>/{{ $shop->cheapestUnit() }}</span>
                                        <a class="btn btn-primary btn-sm" href="{{ route('shops.builder', $shop) }}">Build</a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
