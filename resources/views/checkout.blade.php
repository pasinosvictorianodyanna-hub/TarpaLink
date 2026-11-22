@extends('layouts.app')

@section('title', 'Checkout — '.$shop->name)

@section('content')
{{-- The shell — crumbs, step rail, page frame — stays Blade. Only the two
     columns that hold state are Vue: contact fields, fulfillment, payment
     method, and the total the customer is about to agree to.

     The form posts JSON instead of doing a page submit, so a rejected field
     comes back under the input that caused it and the button can say what it is
     doing. The Blade <form> it replaces is still reachable without JavaScript:
     `CheckoutController::store()` answers a non-JSON request with the same
     redirect it always did. --}}
<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Shops</a> <span>/</span>
            <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a> <span>/</span> Checkout
        </div>

        {{-- Errors from the no-JavaScript path. The island renders its own under
             the fields, so this block stays empty when the bundle is running. --}}
        @if ($errors->any())
            <div class="alert alert-err" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="co-steps" style="margin:0 0 20px">
            <div class="co-step done"><span class="n">1</span>Configure</div>
            <div class="co-line" style="width:40px"></div>
            <div class="co-step done"><span class="n">2</span>Checkout</div>
            <div class="co-line" style="width:40px"></div>
            <div class="co-step"><span class="n">3</span>Proof</div>
        </div>

        <div id="checkout-form" data-component="checkout-form"
             data-props="{{ json_encode($props, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
            {{-- Replaced by Vue on mount. If the bundle fails to load, this
                 fallback still lets the order be placed the old way rather than
                 showing an empty page at the last step of a purchase. --}}
            <form method="POST" action="{{ route('checkout.store', $shop) }}" class="co-grid">
                @csrf
                <input type="hidden" name="service_id" value="{{ $spec['service_id'] }}">
                <input type="hidden" name="width_in" value="{{ $spec['width_in'] }}">
                <input type="hidden" name="height_in" value="{{ $spec['height_in'] }}">
                <input type="hidden" name="qty" value="{{ $spec['qty'] }}">
                <input type="hidden" name="options" value="{{ collect($spec['options'])->map(fn ($o) => $o['id'].':'.$o['qty'])->implode(',') }}">

                <div>
                    <div class="card co-panel">
                        <div class="co-panel-head">
                            <h4>Contact details</h4>
                        </div>
                        <div class="form-grid">
                            <label>
                                <span class="lbl">Full name</span>
                                <input class="inp" name="contact_name" value="{{ old('contact_name', auth()->user()?->name) }}" required>
                            </label>
                            <label>
                                <span class="lbl">Mobile number</span>
                                <input class="inp" name="contact_phone" value="{{ old('contact_phone', auth()->user()?->phone) }}" required>
                            </label>
                        </div>
                        <label style="display:block;margin-top:12px">
                            <span class="lbl">Email address</span>
                            <input class="inp" type="email" name="contact_email" value="{{ old('contact_email', auth()->user()?->email) }}" required>
                        </label>
                    </div>
                </div>

                <aside>
                    <div class="sum-box">
                        <div class="sum-total">
                            <div class="l"><span class="lbl">Total due</span></div>
                            <div class="v">₱{{ number_format($quote['total'] / 100, 2) }}</div>
                        </div>
                        <div style="padding:14px 18px 18px">
                            @auth
                                <input type="hidden" name="payment_method" value="gcash">
                                <button class="btn btn-primary" style="width:100%;justify-content:center" type="submit">
                                    Pay ₱{{ number_format($quote['total'] / 100, 2) }}
                                </button>
                            @else
                                <a class="btn btn-primary" style="width:100%;justify-content:center" href="{{ route('login') }}">
                                    Sign in to place order
                                </a>
                            @endauth
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </div>
</div>
@endsection
