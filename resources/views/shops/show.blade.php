@extends('layouts.app')

@section('title', $shop->name.' — TarpaLink')

@section('content')
@php
    $initials = \Illuminate\Support\Str::of($shop->name)->explode(' ')
        ->filter(fn ($p) => preg_match('/^\p{L}/u', $p))->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $unit = fn ($u) => $u === 'sqft' ? 'sq ft' : 'pc';
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Shops</a> <span>/</span>
            <a href="{{ route('shops.index', ['city' => $shop->city]) }}">{{ $shop->city }}</a> <span>/</span> {{ $shop->name }}
        </div>

        <div class="shop-hero" style="background:{{ $shop->coverGradient() }}">
            <div class="dots halftone"></div><div class="crop"></div>
            <span class="shop-hero-name">{{ $shop->name }}</span>
            <div class="shop-hero-pills">
                <span class="pill">{{ $shop->turnaroundLabel() }}</span>
                <span class="pill">Verified partner</span>
            </div>
        </div>

        <div class="shop-id">
            <span class="avatar lg" style="background:{{ $shop->coverGradient() }}">{{ $initials }}</span>
            <div style="min-width:0">
                <div class="shop-id-name">
                    {{ $shop->name }}
                    <svg class="vf vf-dot" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
                </div>
                <div class="shop-id-meta">
                    <span class="st">★ {{ number_format($shop->rating, 1) }}</span>
                    <span>{{ $shop->jobs_done }} reviews</span>
                    <span>·</span>
                    <span>{{ $shop->address }}, {{ $shop->city }}</span>
                    <span>·</span>
                    <span>{{ $shop->distanceLabel() }}</span>
                    <span>·</span>
                    <span class="open">{{ $shop->turnaroundLabel() }} turnaround</span>
                </div>
            </div>
        </div>

        <div class="shop-tabs">
            <a class="on" href="#prices">Services &amp; pricing</a>
        </div>

        <div class="shop-grid">
            <div>
                <div class="card shop-prices">
                    <div class="shop-prices-head">
                        <h4>Price list</h4>
                        <span class="tiny">Updated {{ $shop->updated_at->diffForHumans() }}</span>
                    </div>

                    @foreach ($services as $service)
                        @php $from = $service->tiers->min('unit_price'); @endphp
                        <a class="price-row" href="{{ route('shops.builder', [$shop, 'service_id' => $service->id]) }}">
                            <span class="ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9h17"/></svg>
                            </span>
                            <div style="min-width:0">
                                <b>{{ $service->name }} — {{ $service->blurb }}</b>
                                <span class="d">{{ $service->tiers->count() }} size tiers · {{ $service->options->count() }} finishing options</span>
                            </div>
                            <div class="rate">
                                <b>₱{{ number_format($from / 100, 2) }}/{{ $unit($service->unit) }}</b>
                                <span class="d">from</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="card" style="padding:20px 24px;margin-top:16px">
                    <h4 style="font-size:14px;margin-bottom:8px">About this shop</h4>
                    <p class="small" style="margin:0">{{ $shop->description }}</p>
                    <div class="tiny" style="margin-top:12px">{{ $shop->address }}, {{ $shop->city }} · {{ $shop->phone }}</div>
                </div>
            </div>

            <aside>
                <div class="card side-card">
                    <div class="tiny">from</div>
                    <div class="from">
                        <b>₱{{ number_format(($shop->cheapestRate() ?? 0) / 100, 2) }}</b>
                        <span>/ {{ $unit($shop->cheapestUnit()) }}</span>
                    </div>

                    <div class="kv" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line-2)">
                        <span>Turnaround</span><b>{{ $shop->turnaroundLabel() }}</b>
                    </div>
                    <div class="kv">
                        <span>Rush option</span><b>{{ $shop->accepts_rush ? 'Available' : 'Not offered' }}</b>
                    </div>
                    <div class="kv">
                        <span>Pickup</span><b>Available</b>
                    </div>
                    <div class="kv">
                        <span>Delivery</span><b>{{ $shop->offers_delivery ? 'Available' : 'Not offered' }}</b>
                    </div>

                    <a class="btn btn-primary" style="width:100%;justify-content:center;margin-top:16px"
                       href="{{ route('shops.builder', $shop) }}">Start an order</a>

                    <p class="tiny" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line-2);line-height:1.55">
                        Payment is held by TarpaLink and released to the shop only after you confirm pickup.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
