{{--
    The console's icon set, named by the data rather than embedded in it.
    A `$name` the set does not know renders nothing rather than an empty box —
    the label beside it still reads.
--}}
@switch($name)
    @case('grid')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        @break
    @case('inbox')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3"/></svg>
        @break
    @case('board')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="10" rx="1.5"/><rect x="17" y="4" width="4" height="13" rx="1.5"/></svg>
        @break
    @case('table')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg>
        @break
    @case('box')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
        @break
    @case('tag')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.4"/></svg>
        @break
    @case('chart')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/></svg>
        @break
    @case('doc')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
        @break
@endswitch
