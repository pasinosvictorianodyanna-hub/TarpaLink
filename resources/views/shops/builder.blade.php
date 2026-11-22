@extends('layouts.app')

@section('title', 'Build an order — '.$shop->name)

@section('content')
<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Print shops</a> <span>/</span>
            <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a> <span>/</span> New order
        </div>

        @php
            $initialService = $shop->services->firstWhere('id', $payload['initialServiceId']) ?? $shop->services->first();
        @endphp
        <div class="page-head">
            <div>
                <h1 class="h3" id="builder-title">Build your {{ strtolower($initialService->name) }}</h1>
                <p>Every change updates the price instantly — this is the shop's actual matrix.</p>
            </div>
            <div class="co-steps" style="margin:0">
                <div class="co-step done"><span class="n">1</span>Configure</div>
                <div class="co-line" style="width:40px"></div>
                <div class="co-step"><span class="n">2</span>Checkout</div>
                <div class="co-line" style="width:40px"></div>
                <div class="co-step"><span class="n">3</span>Proof</div>
            </div>
        </div>

        <div id="order-builder" data-component="order-builder"
             data-props="{{ json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
            <div class="card" style="padding:40px;text-align:center;color:var(--muted)">Loading the price matrix…</div>
        </div>
    </div>
</div>
@endsection
