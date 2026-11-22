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
    //
    // Every entry is the same shape — label, destination, whether it is the
    // current screen, and a reason when there is no destination yet. One shape
    // means one loop in the view, and no branch can quietly render a link to
    // nowhere.
    if (request()->routeIs('checkout.*')) {
        $barLinks = [
            ['label' => 'Shops', 'url' => route('shops.index'), 'on' => request()->routeIs('shops.*'), 'why' => null],
        ];
    } elseif (request()->routeIs('shops.builder')) {
        $barLinks = [
            ['label' => 'Shops', 'url' => route('shops.index'), 'on' => false, 'why' => null],
            ['label' => 'New order', 'url' => route('shops.builder', request()->route('shop')), 'on' => true, 'why' => null],
            ['label' => 'My jobs', 'url' => route('account'), 'on' => false, 'why' => null],
        ];
    } else {
        // "My jobs" is the customer's list. A shop owner signing in was shown it
        // — empty, because their jobs are the ones customers place with them —
        // and an admin was shown the same. Each role gets the door it needs.
        if ($me->isAdmin()) {
            $barLinks = [
                ['label' => 'Shops', 'url' => route('shops.index'), 'on' => request()->routeIs('shops.*'), 'why' => null],
                ['label' => 'Admin panel', 'url' => route('filament.admin.pages.dashboard'), 'on' => request()->routeIs('filament.admin.*'), 'why' => null],
                ['label' => 'Profile', 'url' => route('profile.edit'), 'on' => request()->routeIs('profile.*'), 'why' => null],
            ];
        } elseif ($me->isShopOwner()) {
            $barLinks = [
                ['label' => 'Shops', 'url' => route('shops.index'), 'on' => request()->routeIs('shops.*'), 'why' => null],
                ['label' => 'Console', 'url' => route('console.dashboard'), 'on' => request()->routeIs('console.*'), 'why' => null],
                ['label' => 'Profile', 'url' => route('profile.edit'), 'on' => request()->routeIs('profile.*'), 'why' => null],
            ];
        } else {
            // The customer's bar is built below, once the unfinished order and
            // the current shop are known — it needs both to decide what to show.
            $barLinks = [];
        }
    }

    // The bell counts proofs waiting on a customer. A shop owner and an admin
    // have no such list, so it would be a permanent zero that leads nowhere.
    $showBell = ! $me->isAdmin() && ! $me->isShopOwner();

    /*
     |--------------------------------------------------------------------------
     | Customer navigation
     |--------------------------------------------------------------------------
     |
     | The bar used to offer three doors — Shops, My jobs, Profile — and left the
     | other four screens of the customer journey reachable only by walking into
     | a shop or a job. A customer who left mid-order had nothing on screen
     | telling them where they were or how to get back.
     |
     | All seven now sit in the bar itself, in the order of the journey. The ones
     | that need a shop or a job are rendered as dimmed text rather than links
     | until that context exists — a nav item that looks clickable and quietly
     | goes nowhere is the thing this is meant to prevent.
     */
    $isCustomer = ! $me->isAdmin() && ! $me->isShopOwner();

    $resume = null;
    $currentShop = null;

    if ($isCustomer) {
        // The most useful unfinished thing, in the order a customer would want
        // it: a proof waiting on them, then a job at the counter, then an order
        // created but never paid, then anything still being printed.
        $resumeJob = $me->jobs()
            ->whereIn('status', ['awaiting_proof', 'ready', 'quoted', 'approved', 'in_production'])
            ->orderByRaw("CASE status
                WHEN 'awaiting_proof' THEN 0
                WHEN 'ready' THEN 1
                WHEN 'quoted' THEN 2
                WHEN 'approved' THEN 3
                ELSE 4 END")
            ->latest()
            ->first();

        if ($resumeJob !== null) {
            $resume = [
                'label' => match ($resumeJob->status) {
                    'awaiting_proof' => 'Approve proof',
                    'ready' => 'Collect '.$resumeJob->reference,
                    // Created but never paid. "Finish" would promise an action
                    // the job page cannot offer, so the chip says what is true.
                    'quoted' => 'Unpaid '.$resumeJob->reference,
                    default => 'Track '.$resumeJob->reference,
                },
                'title' => $resumeJob->statusLabel().' · '.$resumeJob->shop->name.' · '.$resumeJob->reference,
                'url' => route('jobs.show', $resumeJob),
                'urgent' => in_array($resumeJob->status, ['awaiting_proof', 'ready'], true),
            ];
        }

        // Builder and checkout need a shop in the URL, and order status needs a
        // job. Take whichever the current page is about, so those three point at
        // something real instead of at a generic page.
        $currentShop = request()->route('shop');

        if (! $currentShop instanceof \App\Models\Shop) {
            $boundJob = request()->route('job');
            $currentShop = $boundJob instanceof \App\Models\Job ? $boundJob->shop : null;
        }

        $barLinks = [
            [
                'label' => 'Shop directory',
                'url' => route('shops.index'),
                'on' => request()->routeIs('shops.index'),
                'why' => null,
            ],
            [
                'label' => 'Shop profile',
                'url' => $currentShop ? route('shops.show', $currentShop) : null,
                'on' => request()->routeIs('shops.show'),
                'why' => 'Open a shop first',
            ],
            [
                'label' => 'Order builder',
                'url' => $currentShop ? route('shops.builder', $currentShop) : null,
                'on' => request()->routeIs('shops.builder'),
                'why' => 'Open a shop first',
            ],
            [
                'label' => 'Checkout',
                'url' => $currentShop ? route('checkout.show', $currentShop) : null,
                'on' => request()->routeIs('checkout.*'),
                'why' => 'Build an order first',
            ],
            [
                'label' => 'Order status',
                'url' => $resume['url'] ?? null,
                'on' => request()->routeIs('jobs.*'),
                'why' => 'No order in progress',
            ],
            [
                'label' => 'My jobs',
                'url' => route('account'),
                'on' => request()->routeIs('account'),
                'why' => null,
            ],
            [
                'label' => 'Profile',
                'url' => route('profile.edit'),
                'on' => request()->routeIs('profile.*'),
                'why' => null,
            ],
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
      @foreach ($barLinks as $link)
        @if ($link['url'])
          <a href="{{ $link['url'] }}" class="{{ $link['on'] ? 'on' : '' }}">{{ $link['label'] }}</a>
        @else
          {{-- Dimmed, not hidden, and not a link: the screen exists, it just
               needs a shop or a job first, and the title says which. --}}
          <span class="nav-off" aria-disabled="true" title="{{ $link['why'] }}">{{ $link['label'] }}</span>
        @endif
      @endforeach
    </div>
    <div class="appbar-right">
      {{-- The one thing a customer who left mid-order actually needs: a way back
           to the job that is waiting on them, named after what it is waiting for. --}}
      @if ($resume)
        <a class="resume-chip {{ $resume['urgent'] ? 'urgent' : '' }}" href="{{ $resume['url'] }}" title="{{ $resume['title'] }}">
          <i aria-hidden="true"></i><span>{{ $resume['label'] }}</span>
        </a>
      @endif

      @if ($showBell)
        <a class="icon-btn bell-btn" href="{{ route('account') }}" aria-label="Jobs waiting on you" title="{{ $waiting }} job(s) waiting on you">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
            <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>
          </svg>
          @if ($waiting > 0)<i class="dot"></i>@endif
        </a>
      @endif

      <a class="avatar" href="{{ route('profile.edit') }}" title="{{ $me->name }}">{{ $initials }}</a>

      {{--
          Sign out belongs in the header, not only on the profile screen.
          Filament answers 403 (not a redirect) to an authenticated user who is
          not allowed into a panel, so someone signed in as a customer who opens
          /admin is stuck unless they can find a way out. The profile page was
          the only one offering it.
      --}}
      <form method="POST" action="{{ route('logout') }}" style="display:inline-flex;margin:0">
        @csrf
        <button type="submit" class="icon-btn" aria-label="Sign out" title="Sign out" style="padding:0;cursor:pointer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>
          </svg>
        </button>
      </form>
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
        {{-- The directory is a signed-in destination: the app bar carries
             "Shops" once there is a customer to show it to. The public nav
             points at the marketing sections and lets the "Get a quote" button
             carry the one route into /shops. --}}
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

<footer class="footer">
    <div class="wrap">
      <div class="foot-grid">
        <div class="foot-brand">
          <a class="logo" href="{{ route('home') }}">
            <span class="logo-mark">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/></svg>
            </span>
            TarpaLink
          </a>
          @if (! empty($footerBlurb))
            <p>{{ $footerBlurb }}</p>
          @endif
          <div class="cmyk" aria-hidden="true"><i style="background:#0092E4"></i><i style="background:#E8265E"></i><i style="background:#F5B700"></i><i style="background:#0C0F0E"></i></div>
        </div>
        <div>
          <h5>Customers</h5>
          <a href="{{ route('shops.index') }}">Browse shops</a>
          <a href="{{ route('home') }}#pricing">Price calculator</a>
          {{-- /account sits behind `auth`, so a guest is bounced to the login and
               returned here afterwards — better than linking straight at the form. --}}
          <a href="{{ route('account') }}">Job status</a>
          <a href="{{ route('page', 'help') }}">Help center</a>
        </div>
        <div>
          <h5>Shop owners</h5>
          <a href="{{ route('page', 'open-a-shop') }}">Open a shop</a>
          <a href="{{ route('page', 'pricing') }}">Pricing &amp; fees</a>
          {{-- The console sits behind `auth`, so this is its front door for
               owners and a sign-in wall for everyone else. --}}
          <a href="{{ route('console.dashboard') }}">Shop console</a>
          <a href="{{ route('page', 'partners') }}">Partner guidelines</a>
        </div>
        <div>
          <h5>Company</h5>
          <a href="{{ route('page', 'about') }}">About</a>
          <a href="{{ route('page', 'contact') }}">Contact</a>
          <a href="{{ route('page', 'privacy') }}">Privacy</a>
          <a href="{{ route('page', 'terms') }}">Terms</a>
          {{-- The CMS had no door. /admin existed and was reachable by typing
               it, which is not a route — an administrator who is not already
               signed in has no way to find it. This points at the panel's own
               login: the link only renders for guests, so it is never a
               second "Admin panel" link competing with the header one. --}}
          @guest
            <a href="{{ route('filament.admin.auth.login') }}">Admin sign in</a>
          @endguest
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
