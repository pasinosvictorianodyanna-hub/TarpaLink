@extends('layouts.app')

@section('title', 'My jobs — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">Home <span>/</span> My jobs</div>

        <div class="page-head">
            <div>
                <h3 class="h3">My jobs</h3>
                <p>Everything you have ordered, across every shop.</p>
            </div>
            <a class="btn btn-primary btn-sm" href="{{ route('shops.index') }}">New order</a>
        </div>

        <div class="counters">
            <div class="counter"><span class="n">{{ $counts['active'] }}</span><span class="l">In progress</span></div>
            <div class="counter"><span class="n">{{ $counts['ready'] }}</span><span class="l">Ready for pickup</span></div>
            <div class="counter"><span class="n">{{ $counts['done'] }}</span><span class="l">Completed</span></div>
        </div>

        @if ($needsProof)
            <div class="card" style="padding:18px 20px;border-left:3px solid var(--magenta);margin-bottom:16px">
                <b style="font-size:13.5px">One job needs your approval</b>
                <p class="small" style="margin-top:4px">
                    {{ $needsProof->service->name }} at {{ $needsProof->shop->name }} — the proof is waiting.
                </p>
                <a class="btn btn-primary btn-sm" style="margin-top:12px" href="{{ route('jobs.show', $needsProof) }}">
                    Review proof
                </a>
            </div>
        @endif

        @forelse ($jobs as $job)
            <div class="card job-row">
                <div class="job-thumb" style="background:{{ $job->shop->coverGradient() }}"></div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                        <b style="font-size:13.5px;letter-spacing:-.01em">{{ $job->service->name }}</b>
                        <span class="status st-{{ $job->statusTone() }}"><i></i>{{ $job->statusLabel() }}</span>
                    </div>
                    <div class="small" style="margin-top:4px">
                        {{ $job->shop->name }} · {{ $job->reference }} ·
                        {{ rtrim(rtrim(number_format($job->spec['width_in'], 1), '0'), '.') }} ×
                        {{ rtrim(rtrim(number_format($job->spec['height_in'], 1), '0'), '.') }} in ×
                        {{ $job->spec['qty'] }}
                    </div>
                </div>
                <div style="text-align:right;flex:none">
                    <div style="font-size:14px;font-weight:600;letter-spacing:-.02em">{{ $peso($job->total) }}</div>
                    <div class="tiny" style="margin-top:2px">{{ $job->created_at->format('d M Y') }}</div>
                </div>
                <a class="btn btn-ghost btn-sm" style="flex:none" href="{{ route('jobs.show', $job) }}">View</a>
            </div>
        @empty
            <div class="card" style="padding:48px 24px;text-align:center">
                <b>No jobs yet.</b>
                <p class="small" style="margin-top:6px">Find a shop, set your size, and see the exact price before you order.</p>
                <a class="btn btn-primary btn-sm" style="margin-top:16px" href="{{ route('shops.index') }}">Browse shops</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
