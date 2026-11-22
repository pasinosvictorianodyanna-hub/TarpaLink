{{--
    The shop application.

    It sits under the CMS copy on /open-a-shop because that page is where the
    homepage CTA and the footer both send a prospective shop. This form creates
    the account **and** the request; it never creates a shop, so nothing here is
    legal to trade with until an administrator approves it.

    A signed-in applicant is not asked for a password — they already have one,
    and retyping it would be theatre. The request is attached to their account
    either way.
--}}
@php($applicant = auth()->user())

<div class="card" id="apply" style="padding:26px 24px;margin-top:34px">
    <h2 class="h3" style="margin-bottom:6px">Request a shop account</h2>

    <p class="small" style="margin:0 0 20px;line-height:1.7">
        @if ($applicant)
            You are signed in as <b>{{ $applicant->email }}</b>. This request is attached to that
            account, so approval opens the console without any further setup.
        @else
            Create the account you will run the shop from, and tell us about the shop. An
            administrator reviews every request — usually within one business day. Nothing can
            be sold until it is approved.
        @endif
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

        @unless ($applicant)
            <p class="lbl" style="margin:0 0 10px;font-weight:600">Your account</p>

            <div class="form-grid">
                <label>
                    <span class="lbl">Your name</span>
                    <input class="inp" type="text" name="contact_name" value="{{ old('contact_name') }}" maxlength="120" required autocomplete="name">
                </label>

                <label>
                    <span class="lbl">Email</span>
                    <input class="inp" type="email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="email">
                </label>

                <label>
                    <span class="lbl">Password</span>
                    <input class="inp" type="password" name="password" minlength="8" required autocomplete="new-password">
                </label>

                <label>
                    <span class="lbl">Confirm password</span>
                    <input class="inp" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </label>
            </div>

            <p class="lbl" style="margin:22px 0 10px;font-weight:600">The shop</p>
        @endunless

        <div class="form-grid">
            <label>
                <span class="lbl">Business name</span>
                <input class="inp" type="text" name="business_name" value="{{ old('business_name') }}" maxlength="120" required autocomplete="organization">
            </label>

            @if ($applicant)
                <label>
                    <span class="lbl">Your name</span>
                    <input class="inp" type="text" name="contact_name" value="{{ old('contact_name', $applicant->name) }}" maxlength="120" required autocomplete="name">
                </label>

                <label>
                    <span class="lbl">Email</span>
                    <input class="inp" type="email" name="email" value="{{ old('email', $applicant->email) }}" maxlength="190" required autocomplete="email">
                </label>
            @endif

            <label>
                <span class="lbl">Mobile</span>
                <input class="inp" type="text" name="phone" value="{{ old('phone', $applicant?->phone) }}" maxlength="40" autocomplete="tel" placeholder="0917 000 0000">
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
            @if ($applicant)
                Sending this creates nothing but a request. Your account stays exactly as it is
                until an administrator approves the shop.
            @else
                Sending this creates your account and a request. No shop exists yet, and your
                account cannot open the console until an administrator approves it.
            @endif
        </p>
    </form>
</div>
