@extends('layouts.app')

@section('title', 'Print shops — TarpaLink')

@section('content')
<div class="app">
    <div class="app-body">
        <div class="crumbs">Home <span>/</span> Print shops</div>

        <div class="page-head">
            <div>
                <h3 class="h3">{{ $shops->count() }} print shops</h3>
                <p>Every shop publishes its own price matrix, so the number you see is the number you pay.</p>
            </div>
            <form method="GET" action="{{ route('shops.index') }}" class="dir-search">
                <input type="text" name="q" value="{{ $search }}" placeholder="Tarpaulin, stickers, flyers…">
                <button class="btn btn-primary btn-sm" type="submit">Search</button>
            </form>
        </div>

        <div class="dir-chips">
            <a class="chip-btn {{ $city === '' ? 'on' : '' }}" href="{{ route('shops.index', ['q' => $search]) }}">All cities</a>
            @foreach ($cities as $option)
                <a class="chip-btn {{ $city === $option ? 'on' : '' }}"
                   href="{{ route('shops.index', ['q' => $search, 'city' => $option]) }}">{{ $option }}</a>
            @endforeach
        </div>

        @if ($shops->isEmpty())
            <div class="card" style="padding:48px 24px;text-align:center">
                <b>No shops match that search.</b>
                <p class="small" style="margin-top:6px">Try a different service or clear the city filter.</p>
                <a class="btn btn-ghost btn-sm" style="margin-top:16px" href="{{ route('shops.index') }}">Clear filters</a>
            </div>
        @else
            <div class="shops">
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
                                <span>({{ $shop->jobs_done }})</span><span>·</span><span>{{ $shop->city }}</span>
                            </div>
                            <p class="shop-desc">{{ $shop->description }}</p>
                            <div class="shop-foot">
                                <span class="shop-price">from <b>₱{{ number_format(($shop->cheapestRate() ?? 0) / 100) }}</b>/{{ $shop->cheapestUnit() }}</span>
                                <span class="tag {{ $shop->accepts_rush ? 'tag-g' : 'tag-m' }}">{{ $shop->accepts_rush ? '24h rush' : 'Standard' }}</span>
                            </div>
                            <a class="btn btn-primary btn-sm" style="width:100%;justify-content:center;margin-top:12px"
                               href="{{ route('shops.builder', $shop) }}">Build an order</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
