<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'TarpaLink — print, priced before you commit')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@auth
  @php
    $me = auth()->user();
    $initials = \Illuminate\Support\Str::of($me->name)
        ->explode(' ')->filter(fn ($p) => preg_match('/^\p{L}/u', $p))->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $waiting = $me->jobs()->where('status', 'awaiting_proof')->count();

    // The design trims the bar down on the two screens that are steps in one
    // flow rather than places you navigate to: checkout is a leg of the builder,
    // and the builder itself replaces "Profile" with the draft it belongs to.
    if (request()->routeIs('checkout.*')) {
        $barLinks = [['Shops', route('shops.index'), request()->routeIs('shops.*')]];
    } elseif (request()->routeIs('shops.builder')) {
        $barLinks = [
            ['Shops', route('shops.index'), false],
            ['New order', route('shops.builder', request()->route('shop')), true],
            ['My jobs', route('account'), false],
        ];
    } else {
        $barLinks = [
            ['Shops', route('shops.index'), request()->routeIs('shops.*')],
            ['My jobs', route('account'), request()->routeIs('account') || request()->routeIs('jobs.*')],
            ['Profile', route('profile.edit'), request()->routeIs('profile.*')],
        ];
    }
  @endphp

  <nav class="appbar">
    <a class="logo" href="{{ route('home') }}">
      <span class="logo-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
          <path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/>
        </svg>
      </span>
      TarpaLink
    </a>
    <div class="appbar-links">
      @foreach ($barLinks as [$label, $href, $on])
        <a href="{{ $href }}" class="{{ $on ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
    <div class="appbar-right">
      <a class="icon-btn" href="{{ route('account') }}" aria-label="Jobs waiting on you" title="{{ $waiting }} job(s) waiting on you">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
          <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>
        </svg>
        @if ($waiting > 0)<i class="dot"></i>@endif
      </a>
      <a class="avatar" href="{{ route('profile.edit') }}" title="{{ $me->name }}">{{ $initials }}</a>
    </div>
  </nav>
@else
  <nav class="mk-nav" id="mkNav">
    <div class="wrap mk-nav-in">
      <a class="logo" href="{{ route('home') }}">
        <span class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
            <path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/>
          </svg>
        </span>
        TarpaLink
      </a>
      <div class="mk-links">
        <a href="{{ route('shops.index') }}">Print shops</a>
        <a href="{{ route('home') }}#pricing">Services</a>
        <a href="{{ route('home') }}#how">How it works</a>
        <a href="{{ route('home') }}#owners">For shop owners</a>
      </div>
      <div class="mk-actions">
        <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
        <a class="btn btn-primary btn-sm" href="{{ route('shops.index') }}">Get a quote</a>
      </div>
    </div>
  </nav>
@endauth

@if (session('status'))
  <div class="wrap" style="padding-top:14px">
    <div class="alert">{{ session('status') }}</div>
  </div>
@endif

@yield('content')

@guest
<footer class="footer">
    <div class="wrap">
      <div class="foot-grid">
        <div class="foot-brand">
          <a class="logo" href="#">
            <span class="logo-mark">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/></svg>
            </span>
            TarpaLink
          </a>
          @if (! empty($footerBlurb))
            <p>{{ $footerBlurb }}</p>
          @endif
          <div class="cmyk"><i style="background:#0092E4"></i><i style="background:#E8265E"></i><i style="background:#F5B700"></i><i style="background:#0C0F0E"></i></div>
        </div>
        <div>
          <h5>Customers</h5>
          <a href="#">Browse shops</a><a href="#">Price calculator</a><a href="#">Job status</a><a href="#">Help center</a>
        </div>
        <div>
          <h5>Shop owners</h5>
          <a href="#">Open a shop</a><a href="#">Pricing &amp; fees</a><a href="#">Shop console</a><a href="#">Partner guidelines</a>
        </div>
        <div>
          <h5>Company</h5>
          <a href="#">About</a><a href="#">Contact</a><a href="#">Privacy</a><a href="#">Terms</a>
        </div>
      </div>
      <div class="foot-bot">
        <span>© 2026 TarpaLink. A capstone project — Web Systems &amp; Technologies 2.</span>
        <span>Metro Manila, Philippines · ₱ PHP</span>
      </div>
    </div>
  </footer>
@endguest

</body>
</html>
