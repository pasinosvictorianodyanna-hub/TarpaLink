@extends('layouts.app')

@section('title', 'Profile — TarpaLink')

@section('content')
<div class="app">
    <div class="app-body">
        {{-- One column for the heading and the cards, the way the prototype has
             it: a centred 560px stack, not a full-width head over a centred card. --}}
        <div class="pf-page">
            <div class="crumbs">Home <span>/</span> Profile</div>

            <div class="page-head">
                <div>
                    <h3 class="h3">Profile</h3>
                    <p>Your details are used on job orders and receipts.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="{{ route('account') }}">My jobs</a>
            </div>

            <div id="profile-form" data-component="profile-form"
                 data-props="{{ json_encode($props, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                {{-- Replaced by Vue on mount; the fallback keeps the page usable if
                     the bundle fails to load, rather than showing an empty column. --}}
                <div class="card" style="padding:40px;text-align:center;color:var(--muted)">
                    Loading your details…
                </div>
            </div>

            {{-- The app bar carries the identity, not the actions, so signing out
                 lives here — otherwise there is no way out of the session at all. --}}
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;padding-top:16px;border-top:1px solid var(--line)">
                <span class="tiny">Signed in as {{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
