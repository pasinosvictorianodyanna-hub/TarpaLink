@extends('layouts.app')

@section('title', ($page['title'] ?? 'TarpaLink').' — TarpaLink')

@section('content')
<div class="app">
    <div class="app-body" style="max-width:760px">
        <div class="crumbs">
            <a href="{{ route('home') }}">Home</a> <span>/</span> {{ $page['title'] ?? ucfirst($slug) }}
        </div>

        <header style="padding:8px 0 30px">
            @if (! empty($page['eyebrow']))
                <span class="eyebrow">{{ $page['eyebrow'] }}</span>
            @endif
            <h1 class="page-title" style="font-size:42px;margin-top:14px">{{ $page['title'] ?? ucfirst($slug) }}</h1>
            @if (! empty($page['lede']))
                <p class="lead" style="margin-top:16px;font-size:16px">{{ $page['lede'] }}</p>
            @endif
            @if (! empty($page['updated']))
                <p class="tiny" style="margin-top:14px">Last updated {{ $page['updated'] }}</p>
            @endif
        </header>

        <div class="prose">
            @foreach ($page['blocks'] ?? [] as $block)
                @if (! empty($block['h']))
                    <h2>{{ $block['h'] }}</h2>
                @endif
                @foreach ((array) ($block['p'] ?? []) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            @endforeach
        </div>

        @if ($slug === 'contact')
            <div class="card" style="padding:22px 24px;margin-top:28px">
                <h4 style="font-size:14px;margin-bottom:6px">Email us</h4>
                <p class="small" style="margin:0 0 14px">We answer within one business day, Metro Manila time.</p>
                <a class="btn btn-primary btn-sm" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            </div>
        @endif

        <div style="margin-top:34px;padding-top:20px;border-top:1px solid var(--line)">
            <a class="btn btn-ghost btn-sm" href="{{ route('home') }}">Back to home</a>
        </div>
    </div>
</div>
@endsection
