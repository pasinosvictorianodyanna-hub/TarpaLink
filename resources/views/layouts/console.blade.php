<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Shop console — TarpaLink')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">
  {{--
      Its own stylesheet, not the storefront's. The console is a tool: grey
      canvas, 246px sidebar, tables. None of the paper stock or serif display
      type belongs here, and the storefront should not ship a shell it never
      shows.
  --}}
  @vite(['resources/css/console.css', 'resources/js/app.js'])
</head>
<body>
  @yield('content')
</body>
</html>
