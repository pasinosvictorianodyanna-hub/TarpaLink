@extends('layouts.app')

@section('title', 'Checkout — '.$shop->name)

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $optionString = collect($spec['options'])->map(fn ($o) => $o['id'].':'.$o['qty'])->implode(',');
    $inches = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $editUrl = route('shops.builder', [
        'shop' => $shop,
        'service_id' => $spec['service_id'],
        'width_in' => $spec['width_in'],
        'height_in' => $spec['height_in'],
        'qty' => $spec['qty'],
    ]);
    $user = auth()->user();
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Shops</a> <span>/</span>
            <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a> <span>/</span> Checkout
        </div>

        <div class="co-steps" style="margin:0 0 20px">
            <div class="co-step done"><span class="n">1</span>Configure</div>
            <div class="co-line" style="width:40px"></div>
            <div class="co-step done"><span class="n">2</span>Checkout</div>
            <div class="co-line" style="width:40px"></div>
            <div class="co-step"><span class="n">3</span>Proof</div>
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
                    <div class="card co-panel">
                        <div class="co-panel-head">
                            <h4>Contact details</h4>
                            <span class="note">We'll email the proof here</span>
                        </div>
                        <div class="form-grid">
                            <label>
                                <span class="lbl">Full name</span>
                                <input class="inp" name="contact_name" value="{{ old('contact_name', $user?->name) }}" placeholder="Jomar Mendoza" required>
                            </label>
                            <label>
                                <span class="lbl">Mobile number</span>
                                <input class="inp" name="contact_phone" value="{{ old('contact_phone', $user?->phone) }}" placeholder="+63 917 000 0000" required>
                            </label>
                        </div>
                        <label style="display:block;margin-top:12px">
                            <span class="lbl">Email address</span>
                            <input class="inp" type="email" name="contact_email" value="{{ old('contact_email', $user?->email) }}" placeholder="you@example.com" required>
                        </label>
                    </div>

                    <div class="card co-panel" style="margin-top:16px">
                        <div class="co-panel-head">
                            <h4>Fulfillment</h4>
                            <div class="seg">
                                <label><input type="radio" name="fulfillment" value="pickup" checked>Pickup</label>
                                <label class="{{ $shop->offers_delivery ? '' : 'off' }}">
                                    <input type="radio" name="fulfillment" value="delivery" @disabled(! $shop->offers_delivery)>Delivery
                                </label>
                            </div>
                        </div>

                        <div class="pick-block">
                            <span class="ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 10v10h14V10"/></svg>
                            </span>
                            <div style="min-width:0">
                                <b>{{ $shop->name }}</b>
                                <div class="small">{{ $shop->address }}, {{ $shop->city }} · {{ $shop->distanceLabel() }} away</div>
                                <div class="tiny" style="color:var(--green);margin-top:3px">
                                    Ready for pickup {{ \Illuminate\Support\Carbon::now()->addDays(2)->setTime(17, 0)->format('D, d M · g:i A') }}
                                </div>
                            </div>
                            <a class="btn btn-ghost btn-sm" style="margin-left:auto;flex:none" href="{{ route('shops.show', $shop) }}">Change</a>
                        </div>

                        <label style="display:block;margin-top:14px">
                            <span class="lbl">Who is picking up?</span>
                            <input class="inp" name="pickup_person" value="{{ old('pickup_person') }}" placeholder="Name of the person collecting (optional)">
                        </label>
                        <label style="display:block;margin-top:12px">
                            <span class="lbl">Notes for the shop</span>
                            <textarea class="inp" name="notes" rows="2" placeholder="e.g. Leave 2 inches of white space at the bottom.">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <div class="card co-panel" style="margin-top:16px">
                        <div class="co-panel-head">
                            <h4>Payment method</h4>
                            <span class="tag tag-y">PayMongo · sandbox</span>
                        </div>

                        <div class="pay-row">
                            <label class="pay-opt on"><input type="radio" name="payment_method" value="gcash" checked> GCash</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="maya"> Maya</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="card"> Card</label>
                            <label class="pay-opt"><input type="radio" name="payment_method" value="cod"> Cash at pickup</label>
                        </div>

                        <div class="tiny" style="margin-top:14px;padding:11px 13px;background:var(--tint-y);border-radius:9px;color:#8A5A00">
                            Sandbox mode: test keys only — no real charges are made. The webhook flow still runs, so orders move through every status exactly as they would live.
                        </div>
                    </div>
                </div>

                <aside>
                    <div class="sum-box sticky-card">
                        <div class="co-panel-head" style="padding:18px 18px 0;margin-bottom:0">
                            <h4 style="font-size:13px">Order summary</h4>
                            <a class="note" style="color:var(--ink);text-decoration:underline" href="{{ $editUrl }}">Edit</a>
                        </div>

                        <div style="display:flex;gap:11px;padding:14px 18px 0;align-items:center">
                            <span style="width:42px;height:42px;border-radius:9px;flex:none;background:{{ $shop->coverGradient() }}"></span>
                            <div style="min-width:0">
                                <b style="display:block;font-size:12.5px">{{ $service->name }} · {{ $inches($spec['width_in']) }}×{{ $inches($spec['height_in']) }} in</b>
                                <span class="tiny">{{ $quote['tier'] }} · qty {{ $spec['qty'] }}</span>
                            </div>
                        </div>

                        <div class="sum-lines" style="margin-top:12px">
                            @foreach ($quote['lines'] as $line)
                                <div class="sum-line">
                                    <span>{{ $line['label'] }} × {{ rtrim(rtrim(number_format($line['qty'], 2), '0'), '.') }}</span>
                                    <b>{{ $peso($line['amount']) }}</b>
                                </div>
                            @endforeach
                            @if ($quote['discount'] > 0)
                                <div class="sum-line" style="color:var(--green)">
                                    <span>Bulk tier discount</span>
                                    <b style="color:var(--green)">−{{ $peso($quote['discount']) }}</b>
                                </div>
                            @endif
                            <div class="sum-line"><span>Pickup</span><b>Free</b></div>
                        </div>

                        <div class="sum-total">
                            <div>
                                <div class="l">Total due</div>
                                <div class="tiny" style="margin-top:3px">VAT included</div>
                            </div>
                            <div class="v">{{ $peso($quote['total']) }}</div>
                        </div>

                        <div style="padding:14px 18px 18px">
                            @auth
                                <button class="btn btn-primary" style="width:100%;justify-content:center" type="submit">
                                    Pay {{ $peso($quote['total']) }}
                                </button>
                                <p class="tiny" style="margin-top:9px;text-align:center">You'll get the digital proof within 4 hours.</p>
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

                    <div class="card" style="padding:14px 16px;margin-top:14px">
                        <div class="tiny" style="margin-bottom:9px">Ordering from</div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="avatar" style="background:{{ $shop->coverGradient() }}">
                                {{ \Illuminate\Support\Str::of($shop->name)->explode(' ')->filter(fn ($p) => preg_match('/^\p{L}/u', $p))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                            </span>
                            <div style="min-width:0">
                                <b style="display:block;font-size:12.5px">{{ $shop->name }}</b>
                                <span class="tiny">★ {{ number_format($shop->rating, 1) }} · {{ $shop->jobs_done }} reviews · {{ $shop->turnaroundLabel() }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>
@endsection
