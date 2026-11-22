{{--
    A visible sign-out in the panel topbar.

    Filament's own sign-out is behind the avatar menu. That is one click, but it
    is a click on a picture with no label — an owner looking for a way out of the
    panel does not find it, and before the theme was compiled the whole panel was
    unstyled, so there was nothing to find at all.

    Rendered through the USER_MENU_BEFORE hook in AppServiceProvider, so both
    panels get it and neither panel provider has to know it exists.
--}}
<form method="POST" action="{{ route('logout') }}" style="display:flex;align-items:center;margin:0">
    @csrf
    <button
        type="submit"
        title="Sign out"
        data-sign-out
        style="display:inline-flex;align-items:center;gap:6px;height:2rem;padding:0 .6rem;border-radius:.5rem;border:1px solid var(--gray-200);background:#fff;color:var(--gray-700);font-size:.75rem;font-weight:600;cursor:pointer;white-space:nowrap"
    >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H5a2 2 0 01-2-2V6a2 2 0 012-2h7" />
        </svg>
        Sign out
    </button>
</form>
