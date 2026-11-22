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
        // "My jobs" is the customer's list. A shop owner signing in was shown it
        // — empty, because their jobs are the ones customers place with them —
        // and an admin was shown the same. Each role gets the door it needs.
        if ($me->isAdmin()) {
            $barLinks = [
                ['Shops', route('shops.index'), request()->routeIs('shops.*')],
                ['Admin panel', route('filament.admin.pages.dashboard'), request()->routeIs('filament.admin.*')],
                ['Profile', route('profile.edit'), request()->routeIs('profile.*')],
            ];
        } elseif ($me->isShopOwner()) {
            $barLinks = [
                ['Shops', route('shops.index'), request()->routeIs('shops.*')],
                ['Console', route('console.dashboard'), request()->routeIs('console.*')],
                ['Profile', route('profile.edit'), request()->routeIs('profile.*')],
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
     | Three things fix that: the four destinations that are always meaningful
     | become real links, an unfinished order gets its own chip, and a "More"
     | menu lists all seven screens with a line explaining each — including the
     | ones that need a shop or a job first, which say so instead of linking
     | somewhere that would silently do the wrong thing.
     */
    $isCustomer = ! $me->isAdmin() && ! $me->isShopOwner();

    $resume = null;
    $currentShop = null;
    $moreItems = [];

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
        // job. Take whichever the current page is about so those menu items
        // point at something real rather than at a generic page.
        $currentShop = request()->route('shop');

        if (! $currentShop instanceof \App\Models\Shop) {
            $boundJob = request()->route('job');
            $currentShop = $boundJob instanceof \App\Models\Job ? $boundJob->shop : null;
        }

        $moreItems = [
            [
                'n' => '01',
                'name' => 'Shop directory',
                'desc' => 'Every shop on the platform and the rates each one publishes.',
                'url' => route('shops.index'),
                'why' => null,
                'on' => request()->routeIs('shops.index'),
            ],
            [
                'n' => '02',
                'name' => 'Shop profile',
                'desc' => 'One shop: its services, price tiers and turnaround.',
                'url' => $currentShop ? route('shops.show', $currentShop) : null,
                'why' => $currentShop ? null : 'Open a shop first',
                'on' => request()->routeIs('shops.show'),
            ],
            [
                'n' => '03',
                'name' => 'Order builder',
                'desc' => 'Set the size, quantity and options — the price updates as you type.',
                'url' => $currentShop ? route('shops.builder', $currentShop) : null,
                'why' => $currentShop ? null : 'Open a shop first',
                'on' => request()->routeIs('shops.builder'),
            ],
            [
                'n' => '04',
                'name' => 'Checkout',
                'desc' => 'Confirm the spec, pick a payment method, create the job.',
                'url' => $currentShop ? route('checkout.show', $currentShop) : null,
                'why' => $currentShop ? null : 'Build an order first',
                'on' => request()->routeIs('checkout.*'),
            ],
            [
                'n' => '05',
                'name' => 'Order status',
                'desc' => 'Track a job, approve its proof, download the receipt.',
                'url' => $resume['url'] ?? null,
                'why' => $resume ? null : 'No order in progress',
                'on' => request()->routeIs('jobs.*'),
            ],
            [
                'n' => '06',
                'name' => 'My jobs',
                'desc' => 'Everything you have ordered, across every shop.',
                'url' => route('account'),
                'why' => null,
                'on' => request()->routeIs('account'),
            ],
            [
                'n' => '07',
                'name' => 'Profile',
                'desc' => 'Your name, contact details and password.',
                'url' => route('profile.edit'),
                'why' => null,
                'on' => request()->routeIs('profile.*'),
            ],
        ];

        $barLinks = [
            ['Shops', route('shops.index'), request()->routeIs('shops.*')],
            ['My jobs', route('account'), request()->routeIs('account')],
        ];

        // Only offered when there is an order to look at. A dead "Order status"
        // that lands on an empty list is worse than no link at all.
        if ($resume !== null) {
            $barLinks[] = ['Order status', $resume['url'], request()->routeIs('jobs.*')];
        }

        $barLinks[] = ['Profile', route('profile.edit'), request()->routeIs('profile.*')];
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

      {{-- A <details> element rather than a scripted dropdown: it opens without
           JavaScript, closes on Escape, and is reachable by keyboard for free.
           The script at the foot of this layout only adds outside-click closing. --}}
      @if ($isCustomer)
        <details class="appbar-more">
          <summary class="more-btn" aria-label="All customer screens">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
            <span>More</span>
          </summary>
          <div class="more-panel">
            <div class="more-head">
              <b>Your screens</b>
              <span>The seven steps from browsing to a finished job</span>
            </div>
            @foreach ($moreItems as $item)
              @if ($item['url'])
                <a class="more-item {{ $item['on'] ? 'on' : '' }}" href="{{ $item['url'] }}">
                  <span class="more-n">{{ $item['n'] }}</span>
                  <span class="more-t">
                    <b>{{ $item['name'] }}</b>
                    <i>{{ $item['desc'] }}</i>
                  </span>
                  @if ($item['on'])<span class="more-here">You are here</span>@endif
                </a>
              @else
                <span class="more-item off" aria-disabled="true">
                  <span class="more-n">{{ $item['n'] }}</span>
                  <span class="more-t">
                    <b>{{ $item['name'] }}</b>
                    <i>{{ $item['why'] }}</i>
                  </span>
                </span>
              @endif
            @endforeach
          </div>
        </details>
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

  @if ($isCustomer)
  {{-- <details> already handles open, close-on-second-click and Escape. This adds
       the two things it does not: closing when you click elsewhere, and closing
       when the browser restores the page from its back/forward cache. --}}
  <script>
    (() => {
      const more = document.querySelector('.appbar-more');
      if (!more) return;

      document.addEventListener('click', (e) => {
        if (more.open && !more.contains(e.target)) more.open = false;
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && more.open) {
          more.open = false;
          more.querySelector('summary')?.focus();
        }
      });

      window.addEventListener('pageshow', () => { more.open = false; });
    })();
  </script>
  @endif
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

@guest
<footer class="footer">
    <div class="wrap">
      <div class="foot-grid">
        <div class="foot-brand">
          <a class="logo" href="{{ route('home') }}">
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
               login: the footer only renders for guests, so it is never a
               second "Admin panel" link competing with the header one. --}}
          <a href="{{ route('filament.admin.auth.login') }}">Admin sign in</a>
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
