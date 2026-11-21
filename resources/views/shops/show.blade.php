@extends('layouts.app')

@section('title', $shop->name.' — TarpaLink')

@section('content')
<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Print shops</a> <span>/</span> {{ $shop->name }}
        </div>

        <div class="shop-hero" style="background:{{ $shop->coverGradient() }}">
            <div class="dots halftone"></div><div class="crop"></div>
            <div class="shop-hero-in">
                <div>
                    <h1 class="display" style="font-size:44px;color:#fff">{{ $shop->name }}</h1>
                    <div class="shop-hero-meta">
                        <span>{{ number_format($shop->rating, 1) }}★</span>
                        <span>·</span>
                        <span>{{ $shop->jobs_done }} jobs done</span>
                        <span>·</span>
                        <span>{{ $shop->city }}</span>
                    </div>
                </div>
                <a class="btn btn-primary" href="{{ route('shops.builder', $shop) }}">Start an order</a>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:26px">
            <div>
                <div class="card" style="padding:22px 24px">
                    <h4 style="font-size:15px;font-weight:600;margin-bottom:6px">About this shop</h4>
                    <p class="small">{{ $shop->description }}</p>
                    <div class="tiny" style="margin-top:14px">{{ $shop->address }} · {{ $shop->phone }}</div>
                </div>

                <div class="card" style="padding:0;margin-top:16px;overflow:hidden">
                    <div style="padding:18px 24px 6px"><h4 style="font-size:15px;font-weight:600">Published prices</h4></div>
                    <table class="tbl">
                        <thead>
                            <tr><th>Service</th><th>Size tier</th><th class="r">Rate</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($services as $service)
                                @foreach ($service->tiers as $tier)
                                    <tr>
                                        <td>{{ $loop->first ? $service->name : '' }}</td>
                                        <td style="color:var(--muted)">{{ $tier->label }}</td>
                                        <td class="r" style="font-weight:600">
                                            ₱{{ number_format($tier->unit_price / 100, 2) }} / {{ $service->unit }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="card sticky-card">
                    <div class="tiny">Starting from</div>
                    <div style="font-size:30px;font-weight:600;letter-spacing:-.03em;margin-top:4px">
                        ₱{{ number_format(($shop->cheapestRate() ?? 0) / 100, 2) }}
                        <span class="small" style="font-weight:400;color:var(--muted)">/ {{ $shop->cheapestUnit() }}</span>
                    </div>
                    <div class="small" style="margin-top:10px">Pickup at the counter in {{ $shop->city }}. Price is final — no fee added at checkout.</div>
                    <a class="btn btn-primary" style="width:100%;justify-content:center;margin-top:16px"
                       href="{{ route('shops.builder', $shop) }}">Build an order</a>
                    <div class="tiny" style="margin-top:12px;text-align:center">Proof approval before printing</div>
                </div>

                <div class="card" style="padding:18px;margin-top:14px">
                    <div class="tiny" style="margin-bottom:8px">This shop offers</div>
                    @foreach ($services as $service)
                        <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12.5px;border-bottom:1px solid var(--line-2)">
                            <span>{{ $service->name }}</span>
                            <span style="color:var(--muted)">{{ $service->blurb }}</span>
                        </div>
                    @endforeach
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
