@extends('layouts.app')

@section('content')
  {{--
    The landing page is a Vue page.

    Blade still owns the document: the nav, the footer, the session and the
    CSRF token are all server-rendered around this element. The element is only
    a mount point — Laravel shapes every string (headline split, badge, distance,
    "from" price) into $props before the browser ever sees it, so the component
    decides layout and nothing else. See docs/SPLIT.md.
  --}}
  <div
    data-component="home"
    data-props="{{ json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
  ></div>
@endsection
