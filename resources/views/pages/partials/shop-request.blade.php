{{--
    The shop application.

    It sits under the CMS copy on /open-a-shop because that page is where the
    homepage CTA and the footer both send a prospective shop, and the copy
    already describes this exact step ("create an account, then send us your
    business name…"). This form is the half that was missing.

    It creates a request, not an account: nothing here touches `users` or
    `shops`. An administrator decides, and the account is created at that point.
--}}
<div class="card" id="apply" style="padding:26px 24px;margin-top:34px">
    @if (session('shop_request_sent'))
        <h2 class="h3" style="margin-bottom:8px">Request received</h2>

        <p class="small" style="margin:0 0 14px;line-height:1.7">
            @if (is_string(session('shop_request_sent')))
                We have your details and will email
                <b>{{ session('shop_request_sent') }}</b> once the request has been reviewed.
            @else
                We have your details and will be in touch once the request has been reviewed.
            @endif
        </p>

        <p class="small" style="margin:0;line-height:1.7">
            Nothing is created yet — no account, no storefront. If it is approved, we set the
            shop up and send you a link to choose your own password.
        </p>
    @else
        <h2 class="h3" style="margin-bottom:6px">Request a shop account</h2>

        <p class="small" style="margin:0 0 20px;line-height:1.7">
            Tell us about the shop. An administrator reviews every request — usually within one
            business day — and if it is approved we create the account and email you a link to
            set your own password.
        </p>

        @if ($errors->any())
            <div class="alert alert-err" role="alert" style="margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('shop-request.store') }}">
            @csrf

            {{-- A bot fills every input it can see. This one is off-screen and
                 skipped by the tab order, so a person never meets it. --}}
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="form-grid">
                <label>
                    <span class="lbl">Business name</span>
                    <input class="inp" type="text" name="business_name" value="{{ old('business_name') }}" maxlength="120" required autocomplete="organization">
                </label>

                <label>
                    <span class="lbl">Your name</span>
                    <input class="inp" type="text" name="contact_name" value="{{ old('contact_name', auth()->user()?->name) }}" maxlength="120" required autocomplete="name">
                </label>

                <label>
                    <span class="lbl">Email</span>
                    <input class="inp" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="190" required autocomplete="email">
                </label>

                <label>
                    <span class="lbl">Mobile</span>
                    <input class="inp" type="text" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" maxlength="40" autocomplete="tel" placeholder="0917 000 0000">
                </label>

                <label>
                    <span class="lbl">City</span>
                    <input class="inp" type="text" name="city" value="{{ old('city') }}" maxlength="80" required autocomplete="address-level2">
                </label>

                <label class="full">
                    <span class="lbl">Counter address</span>
                    <input class="inp" type="text" name="address" value="{{ old('address') }}" maxlength="255" required autocomplete="street-address" placeholder="Where customers collect their orders">
                </label>

                <label class="full">
                    <span class="lbl">Services you want to list</span>
                    <textarea class="inp" name="services" rows="3" maxlength="1000" placeholder="Tarpaulin, flyers, stickers, apparel — one per line">{{ old('services') }}</textarea>
                    <span class="tiny" style="display:block;margin-top:5px">Names are enough. Rates are set in the console after approval.</span>
                </label>

                <label class="full">
                    <span class="lbl">Anything else (optional)</span>
                    <textarea class="inp" name="message" rows="3" maxlength="2000" placeholder="Machines you run, turnaround, how long you have been trading…">{{ old('message') }}</textarea>
                </label>
            </div>

            <button class="btn btn-primary" type="submit" style="margin-top:18px">Send the request</button>

            <p class="tiny" style="margin:14px 0 0;line-height:1.7">
                Sending this creates nothing but a request. No account and no storefront exist
                until an administrator approves it.
            </p>
        </form>
    @endif
</div>
