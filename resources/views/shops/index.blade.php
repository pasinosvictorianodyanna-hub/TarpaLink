@extends('layouts.app')

@section('title', 'Print shops — TarpaLink')

@php
    $filtersActive = $city !== '' || $service !== '' || ! empty($turnaround) || ! empty($options) || ($maxPrice !== null && $maxPrice !== '');
@endphp

@section('content')
<div class="app">
    <div class="app-body">
        <div class="crumbs">Home <span>/</span> Print shops</div>

        <div class="page-head">
            <div>
                <h3 class="h3">{{ $shops->count() }} {{ Str::plural('print shop', $shops->count()) }}</h3>
                <p>Every shop publishes its own price matrix, so the number you see is the number you pay.</p>
            </div>
            <form method="GET" action="{{ route('shops.index') }}" class="dir-search">
                <input type="text" name="q" value="{{ $search }}" placeholder="Shop name or area…">
                <button class="btn btn-primary btn-sm" type="submit">Search</button>
            </form>
        </div>

        {{-- Category pills: the fastest filter, so it sits above everything else. --}}
        <div class="dir-chips">
            <a class="chip-btn {{ $service === '' ? 'on' : '' }}"
               href="{{ route('shops.index', array_filter(['q' => $search, 'city' => $city, 'max' => $maxPrice])) }}">All services</a>
            @foreach ($serviceTypes as $type)
                <a class="chip-btn {{ $service === $type['slug'] ? 'on' : '' }}"
                   href="{{ route('shops.index', array_filter(['q' => $search, 'city' => $city, 'service' => $type['slug'], 'max' => $maxPrice])) }}">
                    {{ $type['name'] }} <span class="chip-n">{{ $type['count'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="dir-grid">
            <aside class="dir-rail">
                <form method="GET" action="{{ route('shops.index') }}" id="dir-filters">
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="city" value="{{ $city }}">
                    <input type="hidden" name="service" value="{{ $service }}">

                    <div class="rail-head">
                        <span>Filters</span>
                        @if ($filtersActive)
                            <a href="{{ route('shops.index') }}">Clear</a>
                        @endif
                    </div>

                    <div class="rail-group">
                        <h5>City</h5>
                        <select name="city" class="rail-select" onchange="this.form.submit()">
                            <option value="">All cities</option>
                            @foreach ($cities as $option)
                                <option value="{{ $option }}" @selected($city === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rail-group">
                        <h5>Max price <span class="rail-note">from ₱{{ $maxPrice ?: $priceCeiling }} / unit</span></h5>
                        <input type="range" name="max" min="10" max="{{ $priceCeiling }}" step="5"
                               value="{{ $maxPrice ?: $priceCeiling }}" class="rail-range"
                               oninput="this.nextElementSibling.textContent = '₱' + this.value"
                               onchange="this.form.submit()">
                        <div class="rail-scale"><span>₱10</span><span>₱{{ $maxPrice ?: $priceCeiling }}</span><span>₱{{ $priceCeiling }}</span></div>
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
                    {{-- A scatter of where the shops actually are. No tile server is
                         wired yet, so this is honest about being a map of positions
                         rather than pretending to be Google Maps. --}}
                    <div class="dir-map">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="dir-map-grid" aria-hidden="true">
                            @for ($i = 10; $i < 100; $i += 12)
                                <line x1="{{ $i }}" y1="0" x2="{{ $i }}" y2="100" />
                                <line x1="0" y1="{{ $i }}" x2="100" y2="{{ $i }}" />
                            @endfor
                        </svg>

                        @foreach ($pins as $pin)
                            <a class="dir-pin" style="left:{{ $pin['x'] }}%;top:{{ $pin['y'] }}%"
                               href="{{ $pin['url'] }}" title="{{ $pin['name'] }} · {{ $pin['city'] }}">
                                {{ $pin['n'] }}
                            </a>
                        @endforeach

                        <div class="dir-map-cap">
                            {{ $city ?: 'Metro Manila' }} · {{ $shops->count() }} {{ Str::plural('shop', $shops->count()) }}
                        </div>
                    </div>
                @endif

                @if ($shops->isEmpty())
                    <div class="card" style="padding:48px 24px;text-align:center">
                        <b>No shops match those filters.</b>
                        <p class="small" style="margin-top:6px">Try widening the price range or clearing a filter.</p>
                        <a class="btn btn-ghost btn-sm" style="margin-top:16px" href="{{ route('shops.index') }}">Clear filters</a>
                    </div>
                @else
                    <div class="dir-results">
                        @foreach ($shops as $shop)
                            <article class="shop">
                                <a href="{{ route('shops.show', $shop) }}" class="shop-cover" style="background:{{ $shop->coverGradient() }}">
                                    <div class="dots halftone"></div><div class="crop"></div>
                                    <span class="label">{{ $shop->name }}</span>
                                </a>
                                <div class="shop-body">
                                    <div class="shop-name">
                                        <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a>
                                        <svg class="vf" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 2.4 3.3-.6.6 3.3L21 9.5 19.6 12 21 14.5l-2.7 2.4-.6 3.3-3.3-.6L12 22l-2.4-2.4-3.3.6-.6-3.3L3 14.5 4.4 12 3 9.5l2.7-2.4.6-3.3 3.3.6z" opacity=".9"/></svg>
                                    </div>
                                    <div class="shop-meta">
                                        <span class="st"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>{{ number_format($shop->rating, 1) }}</span>
                                        <span>({{ $shop->jobs_done }})</span><span>·</span><span>{{ $shop->city }}</span><span>·</span><span>{{ $shop->turnaroundLabel() }}</span>
                                    </div>
                                    <p class="shop-desc">{{ $shop->description }}</p>

                                    <div class="shop-badges">
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
