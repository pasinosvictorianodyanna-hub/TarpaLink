@extends('layouts.console')

@section('title', 'Dashboard — TarpaLink shop console')

@section('content')
  {{-- A Vue page: the console shell, the sidebar and the page are all the component. --}}
  <div
    data-component="shop-dashboard"
    data-props="{{ json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
  ></div>
@endsection
