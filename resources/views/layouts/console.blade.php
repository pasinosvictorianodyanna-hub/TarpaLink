@php
    /**
     * The console shell — one sidebar, one top bar, every shop screen.
     *
     * This used to live inside ShopDashboard.vue, which meant the shell existed
     * exactly once, on exactly one screen: every link out of the dashboard left
     * for the Filament panel and the shop owner watched the product change
     * shape mid-click. The shell belongs to the layout, so a new screen cannot
     * accidentally invent a different one.
     *
     * Server-rendered on purpose. The shell has no state beyond the mobile
     * drawer, and rendering it in Blade means forms, validation errors and the
     * CSRF token work on every screen without a JSON layer per form.
     */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($heading['title'] ?? 'Console').' — '.$shell['brand'])</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{--
        The console's own stylesheet, and nothing else. No storefront bundle, no
        Vue runtime: the console is a tool, and a shop owner on a phone should
        not download a marketing site to read their queue.
    --}}
    @vite(['resources/css/console.css'])
</head>
<body>
<div class="fil" id="console-shell">
    <div class="fil-backdrop" data-console-close></div>

    <aside class="fil-side" id="console-sidebar">
        <div class="fil-brand">
            <a class="fil-brand-link" href="{{ route('console.dashboard') }}">
                <span class="lm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/>
                    </svg>
                </span>
                {{ $shell['brand'] }}
            </a>
            <small>{{ $shell['badge'] }}</small>
            <button type="button" class="fil-close" aria-label="Close navigation menu" data-console-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        @if (count($shell['shops']) > 1)
            {{--
                Switching shops used to live only in the Filament panel, so an
                owner of two shops had to leave the console to change shops —
                the one click that still changed the shell underneath them.
            --}}
            <div class="fil-switch">
                <form method="POST" action="{{ route('console.shop') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <select id="console-shop" name="shop_id" aria-label="Switch shop" onchange="this.form.submit()">
                        @foreach ($shell['shops'] as $option)
                            <option value="{{ $option['id'] }}" @selected($option['id'] === $shell['shopId'])>{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        @endif

        <nav class="fil-nav" aria-label="Shop console navigation">
            @foreach ($shell['groups'] as $group)
                <div class="fil-group">{{ $group['label'] }}</div>

                @foreach ($group['items'] as $item)
                    <a
                        class="fil-item {{ $item['active'] ? 'on' : '' }}"
                        href="{{ $item['url'] }}"
                        @if ($item['active']) aria-current="page" @endif
                    >
                        @include('console.partials.icon', ['name' => $item['icon']])
                        {{ $item['label'] }}
                        @if ($item['badge'])
                            <span class="bdg">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="fil-user">
            <span class="av">{{ $shell['user']['initials'] }}</span>
            <div><b>{{ $shell['user']['name'] }}</b><span>{{ $shell['user']['role'] }}</span></div>

            {{-- A plain form POST, so signing out works with JavaScript off. --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="out" type="submit" title="Sign out" aria-label="Sign out">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
                        <path d="M15 17l5-5-5-5"/><path d="M20 12H9"/>
                        <path d="M12 20H5a2 2 0 01-2-2V6a2 2 0 012-2h7"/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <div class="fil-main">
        <div class="fil-top">
            <button
                type="button"
                class="fil-burger"
                id="console-burger"
                aria-label="Open navigation menu"
                aria-controls="console-sidebar"
                aria-expanded="false"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

            <div class="fil-crumb">
                <span>{{ $shell['brand'] }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                <b>{{ $heading['title'] ?? '' }}</b>
            </div>

            <div class="fil-top-right">
                <a class="fil-search" href="{{ route('console.jobs') }}" aria-label="Search jobs in the queue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <span>Search jobs</span>
                    <span class="fil-kbd">Queue</span>
                </a>
                <a class="fil-ico" href="{{ route('console.reports.export') }}" title="Export payouts CSV" aria-label="Export payouts CSV">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 21h16"/></svg>
                </a>
            </div>
        </div>

        <div class="fil-page">
            <div class="fil-ph">
                <div>
                    <h1>{{ $heading['title'] ?? '' }}</h1>
                    @if (! empty($heading['sub']))
                        <p>{{ $heading['sub'] }}</p>
                    @endif
                </div>

                @if (! empty($actions))
                    <div class="fil-actions">
                        @foreach ($actions as $action)
                            <a class="fbtn {{ ($action['style'] ?? 'ghost') === 'primary' ? 'fbtn-primary' : 'fbtn-ghost' }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (session('status'))
                <div class="fil-note" style="border-color:#ABEFC6;background:#ECFDF3;color:#067647">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="fil-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @yield('page')
        </div>
    </div>
</div>

<script>
    // The only behaviour the shell needs: the drawer on narrow screens.
    (function () {
        var shell = document.getElementById('console-shell');
        var burger = document.getElementById('console-burger');

        function set(open) {
            shell.classList.toggle('is-drawer-open', open);
            if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (burger) {
            burger.addEventListener('click', function () {
                set(!shell.classList.contains('is-drawer-open'));
            });
        }

        document.querySelectorAll('[data-console-close]').forEach(function (el) {
            el.addEventListener('click', function () { set(false); });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') set(false);
        });
    })();
</script>
</body>
</html>
