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
      <a href="{{ route('home') }}#how">How it works</a>
      <a href="{{ route('home') }}#owners">For shop owners</a>
      @auth
        <a href="{{ route('account') }}">My jobs</a>
      @endauth
    </div>
    <div class="mk-actions">
      @auth
        <span class="small" style="color:var(--muted)">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
          @csrf
          <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
        </form>
      @else
        <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
      @endauth
      <a class="btn btn-primary btn-sm" href="{{ route('shops.index') }}">Get a quote</a>
    </div>
  </div>
</nav>

@if (session('status'))
  <div class="wrap" style="padding-top:14px">
    <div class="alert">{{ session('status') }}</div>
  </div>
@endif

@yield('content')

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
          <p>The print marketplace for tarpaulins, stickers, flyers and everything else you need on paper.</p>
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

</body>
</html>
