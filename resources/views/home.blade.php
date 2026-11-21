@extends('layouts.app')

@section('content')
  <div class="hero">
    <div class="wrap hero-grid">
      <div>
        <span class="eyebrow">{{ $hero['eyebrow'] }}</span>
        <h1 class="display">{!! nl2br(e($hero['headline'])) !!}</h1>
        <p class="lead">{{ $hero['subheadline'] }}</p>

        <form class="quick-quote" method="GET" action="{{ route('shops.index') }}">
          <label class="qq-field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="text" name="q" placeholder="Tarpaulin, stickers, flyers…">
          </label>
          <label class="qq-field" style="max-width:150px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 21s7-5.4 7-11a7 7 0 10-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <input type="text" name="city" placeholder="Quezon City">
          </label>
          <button class="btn btn-primary" type="submit">Quote</button>
        </form>

        <div class="hero-trust">
          <div class="avatars">
            <span style="background:#0C0F0E">JM</span>
            <span style="background:#E8265E">AR</span>
            <span style="background:#0092E4">KC</span>
            <span style="background:#F5B700;color:#0C0F0E">DL</span>
          </div>
          <div>
            <span class="num">128</span>
            <span class="lbl">verified shops</span>
          </div>
          <div class="sep"></div>
          <div>
            <span class="num">4.8★</span>
            <span class="lbl">avg. shop rating</span>
          </div>
          <div class="sep"></div>
          <div>
            <span class="num">36h</span>
            <span class="lbl">avg. turnaround</span>
          </div>
        </div>
      </div>

      <div class="hero-art">
        <div class="crop" style="color:#B9B3A4">
          <i></i><i></i><i></i><i></i>
          <div class="qc">
            <div class="qc-top">
              <span class="qc-title">Instant quote</span>
              <span class="qc-live"><i></i>Live pricing</span>
            </div>
            <div class="qc-tabs">
              <span class="on">Tarpaulin</span><span>Sticker</span><span>Flyer</span><span>Shirt</span>
            </div>
            <div class="qc-preview">
              <div class="eyelets"></div>
              <div class="art-frame"><b>Grand Opening</b></div>
            </div>
            <div class="qc-rows">
              <div class="qc-row"><span>36 × 24 in · 13oz vinyl</span><b>6 sq ft</b></div>
              <div class="qc-row"><span>Eyelets · hemming</span><b>₱120</b></div>
              <div class="qc-row"><span>Quantity</span><b>3 pcs</b></div>
            </div>
            <div class="qc-total">
              <div><span class="cur">₱</span><span class="amt">1,485</span></div>
              <div class="lbl">All-in price<br>incl. rush fee</div>
            </div>
          </div>
        </div>
        <div class="qc-float f1">
          <span class="ico" style="background:#E7F6EF;color:#0E9F6E">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg>
          </span>
          <div>Proof approved<small>2 minutes ago</small></div>
        </div>
        <div class="qc-float f2">
          <span class="ico" style="background:#FDE9EF;color:#E8265E">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
          </span>
          <div>Ready in 24 hrs<small>Rush production</small></div>
        </div>
      </div>
    </div>

    <div class="marquee">
      <div class="marquee-track">
        <span>Tarpaulin</span><span>Stickers &amp; Labels</span><span>Flyers</span><span>Business Cards</span><span>DTF Shirts</span><span>Standees</span><span>Posters</span><span>Vehicle Stickers</span><span>Mugs &amp; Merch</span><span>Invitations</span>
        <span>Tarpaulin</span><span>Stickers &amp; Labels</span><span>Flyers</span><span>Business Cards</span><span>DTF Shirts</span><span>Standees</span><span>Posters</span><span>Vehicle Stickers</span><span>Mugs &amp; Merch</span><span>Invitations</span>
      </div>
    </div>
  </div>

  <!-- how it works -->
  <div class="sec">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <span class="eyebrow">How it works</span>
          <h2 class="h2">Three steps from idea<br>to printed.</h2>
        </div>
        <p>Every shop publishes its own price matrix, so the number you see is the number you pay.</p>
      </div>
      <div class="steps">
        <div class="step">
          <span class="step-num">01</span>
          <div class="step-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
          </div>
          <h4>Find a shop near you</h4>
          <p>Filter by service, material, rating, lead time and distance on the map.</p>
        </div>
        <div class="step">
          <span class="step-num">02</span>
          <div class="step-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/></svg>
          </div>
          <h4>Build the order, see the price</h4>
          <p>Size, material, finishing, quantity — the quote recalculates as you type.</p>
        </div>
        <div class="step">
          <span class="step-num">03</span>
          <div class="step-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/></svg>
          </div>
          <h4>Approve the proof, then collect</h4>
          <p>The shop sends a digital proof. Approve it, track production, scan to pick up.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- pricing split -->
  <div class="sec" style="padding-top:0">
    <div class="wrap split">
      <div>
        <span class="eyebrow">Transparent pricing</span>
        <h2 class="h2" style="margin-top:14px">The price matrix,<br>out in the open.</h2>
        <p class="lead" style="margin-top:18px;font-size:15.5px">Every shop sets its own rates per square foot, per material, plus minimum charges and finishing fees. TarpaLink computes the total — including quantity discounts and rush surcharges — the moment you change a spec.</p>
        <ul class="bullets">
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg></span>No hidden fees — the breakdown is itemised before checkout.</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg></span>Quantity tiers apply automatically, not on request.</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg></span>Compare the same job across shops in one table.</li>
        </ul>
        <a class="btn btn-primary" style="margin-top:26px" href="#">Try the calculator</a>
      </div>
      <div class="matrix-card">
        <div class="matrix-head">
          <b>Tarpaulin · 13oz vinyl</b>
          <span class="tag tag-c">Best seller</span>
        </div>
        <table class="matrix">
          <thead>
            <tr><th>Size (in)</th><th class="num">Sq ft</th><th class="num">Rate</th><th class="num">Subtotal</th></tr>
          </thead>
          <tbody>
            <tr><td>24 × 36</td><td class="num">6.0</td><td class="num">₱45</td><td class="num">₱270</td></tr>
            <tr class="hl"><td>36 × 48</td><td class="num">12.0</td><td class="num">₱45</td><td class="num">₱540</td></tr>
            <tr><td>48 × 72</td><td class="num">24.0</td><td class="num">₱42</td><td class="num">₱1,008</td></tr>
            <tr><td>72 × 96</td><td class="num">48.0</td><td class="num">₱38</td><td class="num">₱1,824</td></tr>
          </tbody>
        </table>
        <div class="matrix-foot">
          <span>+ Eyelets ₱10/pc · Hemming ₱15/ft</span>
          <b style="color:var(--ink)">Min. ₱250</b>
        </div>
      </div>
    </div>
  </div>

  <!-- featured shops -->
  <div class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <span class="eyebrow">Verified shops</span>
          <h2 class="h2">Trusted by 4,200+ customers.</h2>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ route('shops.index') }}">Browse all {{ $shopCount }} shops</a>
      </div>
            <div class="shops">
        @foreach ($featured as $shop)
        <article class="shop">
          <a href="{{ route('shops.show', $shop) }}" class="shop-cover" style="background:{{ $shop->coverGradient() }}">
            <div class="dots halftone"></div><div class="crop"></div>
            <span class="label">{{ $shop->name }}</span>
          </a>
          <div class="shop-body">
            <div class="shop-name">{{ $shop->name }}
              <svg class="vf" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 2.4 3.3-.6.6 3.3L21 9.5 19.6 12 21 14.5l-2.7 2.4-.6 3.3-3.3-.6L12 22l-2.4-2.4-3.3.6-.6-3.3L3 14.5 4.4 12 3 9.5l2.7-2.4.6-3.3 3.3.6z" opacity=".9"/></svg>
            </div>
            <div class="shop-meta">
              <span class="st"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>{{ number_format($shop->rating, 1) }}</span>
              <span>({{ $shop->jobs_done }})</span><span>·</span><span>{{ $shop->city }}</span>
            </div>
            <div class="shop-foot">
              <span class="shop-price">from <b>₱{{ number_format($shop->cheapestRate() / 100) }}</b>/{{ $shop->cheapestUnit() }}</span>
              <span class="tag tag-g">{{ $shop->accepts_rush ? '24h rush' : 'Standard' }}</span>
            </div>
          </div>
        </article>
        @endforeach
      </div>
    </div>
  </div>

  <!-- owner cta -->
  <div class="wrap">
    <div class="cta-band">
      <div class="grid-paper"></div>
      <div>
        <span class="eyebrow" style="color:#8C9691">For shop owners</span>
        <h2 class="h2" style="margin-top:14px">Stop quoting<br>on Messenger.</h2>
        <p>Publish your price matrix once. Let the platform quote, collect payment, and hand you a production-ready job order.</p>
        <a class="btn btn-lg" style="background:#fff;color:#0C0F0E" href="#">Open a shop account</a>
      </div>
      <div class="cta-stats">
        <div><div class="n">−72%</div><div class="l">time spent quoting</div></div>
        <div><div class="n">2.4×</div><div class="l">repeat customers</div></div>
        <div><div class="n">₱0</div><div class="l">setup fee</div></div>
        <div><div class="n">5%</div><div class="l">per completed order</div></div>
      </div>
    </div>
  </div>

  <!-- testimonials -->
  <div class="sec">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <span class="eyebrow">Reviews</span>
          <h2 class="h2">People who stopped<br>sending "pm sent".</h2>
        </div>
      </div>
      <div class="quotes">
        <div class="quote">
          <div class="stars">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
          </div>
          <p>"I ordered 3 tarpaulins at 11pm and had a price in ten seconds. That used to take two days of asking around."</p>
          <div class="who">
            <span class="av" style="background:#0C0F0E">JM</span>
            <div><b>Jomar M.</b><span>Small business owner · QC</span></div>
          </div>
        </div>
        <div class="quote">
          <div class="stars">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
          </div>
          <p>"The proof approval step saved me. They caught a low-res logo before printing 200 flyers."</p>
          <div class="who">
            <span class="av" style="background:#E8265E">AR</span>
            <div><b>Angela R.</b><span>Marketing associate</span></div>
          </div>
        </div>
        <div class="quote">
          <div class="stars">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z"/></svg>
          </div>
          <p>"As a shop owner, the price matrix is the whole thing. I set it once and it just answers for me."</p>
          <div class="who">
            <span class="av" style="background:#0092E4">KC</span>
            <div><b>Kevin C.</b><span>Owner, Inkwell Press</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
