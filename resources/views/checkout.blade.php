@extends('layouts.app')

@section('title', 'Checkout — '.$shop->name)

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $optionString = collect($spec['options'])->map(fn ($o) => $o['id'].':'.$o['qty'])->implode(',');
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Print shops</a> <span>/</span>
            <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a> <span>/</span> Checkout
        </div>

        <div class="page-head">
            <div>
                <h3 class="h3">Checkout</h3>
                <p>Pickup only — you collect at the counter in {{ $shop->city }}.</p>
            </div>
            <div class="co-steps" style="margin:0">
                <div class="co-step done"><span class="n">1</span>Configure</div>
                <div class="co-line" style="width:40px"></div>
                <div class="co-step done"><span class="n">2</span>Checkout</div>
                <div class="co-line" style="width:40px"></div>
                <div class="co-step"><span class="n">3</span>Proof</div>
            </div>
        </div>

        <form method="POST" action="{{ route('checkout.store', $shop) }}">
            @csrf
            <input type="hidden" name="service_id" value="{{ $spec['service_id'] }}">
            <input type="hidden" name="width_in" value="{{ $spec['width_in'] }}">
            <input type="hidden" name="height_in" value="{{ $spec['height_in'] }}">
            <input type="hidden" name="qty" value="{{ $spec['qty'] }}">
            <input type="hidden" name="options" value="{{ $optionString }}">

            <div class="co-grid">
                <div>
                    <div class="card" style="padding:22px 24px">
                        <h4 style="font-size:15px;font-weight:600;margin-bottom:14px">Who is picking this up?</h4>
                        <div class="form-grid">
                            <label class="field">
                                <span>Full name</span>
                                <input name="name" value="{{ auth()->user()?->name }}" placeholder="Jomar Mendoza">
                            </label>
                            <label class="field">
                                <span>Mobile number</span>
                                <input name="phone" value="{{ auth()->user()?->phone }}" placeholder="+63 917 000 0000">
                            </label>
                        </div>
                        <label class="field" style="margin-top:12px">
                            <span>Notes for the shop</span>
                            <textarea name="notes" rows="2" placeholder="e.g. Leave 2 inches of white space at the bottom."></textarea>
                        </label>
                    </div>

                    <div class="card" style="padding:22px 24px;margin-top:16px">
                        <h4 style="font-size:15px;font-weight:600;margin-bottom:6px">How do you want to pay?</h4>
                        <p class="tiny" style="margin-bottom:14px">Same price either way. Online payments run in sandbox mode — no real charge.</p>

                        <div class="pay-row">
                            <label class="pay-opt on"><input type="radio" name="payment_method" value="gcash" checked> GCash</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="maya"> Maya</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="card"> Card</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="cod"> Cash at pickup</label>
                        </div>

                        <div class="tiny" style="margin-top:14px;padding:11px 13px;background:var(--tint-y);border-radius:9px;color:#8A5A00">
                            Sandbox mode: PayMongo test keys. Nothing is charged, but the payment intent and webhook run end to end.
                        </div>
                    </div>
                </div>

                <aside>
                    <div class="sum-box sticky-card">
                        <div style="padding:18px 18px 0">
                            <div class="tiny">{{ $shop->name }} · {{ $service->name }}</div>
                            <div style="font-size:15px;font-weight:600;margin-top:4px">
                                {{ rtrim(rtrim(number_format($spec['width_in'], 1), '0'), '.') }} × {{ rtrim(rtrim(number_format($spec['height_in'], 1), '0'), '.') }} in
                                · {{ $spec['qty'] }} pc
                            </div>
                        </div>

                        <div class="sum-lines" style="margin-top:12px">
                            @foreach ($quote['lines'] as $line)
                                <div class="sum-line">
                                    <span>{{ $line['label'] }}<template> × {{ $line['qty'] }}</template></span>
                                    <b>{{ $peso($line['amount']) }}</b>
                                </div>
                            @endforeach
                            @if ($quote['discount'] > 0)
                                <div class="sum-line" style="color:var(--green)">
                                    <span>Bulk tier discount</span>
                                    <b style="color:var(--green)">−{{ $peso($quote['discount']) }}</b>
                                </div>
                            @endif
                            <div class="sum-line"><span>Pickup at counter</span><b>Free</b></div>
                        </div>

                        <div class="sum-total">
                            <div>
                                <div class="l">Total</div>
                                <div class="tiny" style="margin-top:3px">{{ $quote['tier'] }}</div>
                            </div>
                            <div class="v">{{ $peso($quote['total']) }}</div>
                        </div>

                        <div style="padding:14px 18px 18px">
                            @auth
                                <button class="btn btn-primary" style="width:100%;justify-content:center" type="submit">
                                    Place order
                                </button>
                            @else
                                <a class="btn btn-primary" style="width:100%;justify-content:center" href="{{ route('login') }}">
                                    Sign in to place order
                                </a>
                                <p class="tiny" style="margin-top:10px;text-align:center">
                                    Demo account: jomar@tarpalink.ph · password
                                </p>
                            @endauth
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>
@endsection
