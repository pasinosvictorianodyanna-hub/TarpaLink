@extends('layouts.app')

@section('title', 'My jobs — TarpaLink')

@section('content')
  {{-- A Vue page: the layout above and below is Blade, the list is a component. --}}
  <div
    data-component="account"
    data-props="{{ json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
  ></div>
@endsection
