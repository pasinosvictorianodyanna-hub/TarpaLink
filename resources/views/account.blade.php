@extends('layouts.app')

@section('title', 'My jobs — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 0);
    $tabs = [
        ['all', 'All', $counts['all']],
        ['active', 'Active', $counts['active']],
        ['ready', 'Ready', $counts['ready']],
        ['done', 'Completed', $counts['done']],
    ];
@endphp

<div class="app">
    <div class="app-body">
        <div class="page-head">
            <div>
                <h1 class="page-title">My jobs</h1>
                <p class="page-sub">{{ $summary }}</p>
            </div>
            <a class="btn btn-primary" href="{{ route('shops.index') }}">Start a new job</a>
        </div>

        <div class="counters">
            <div class="counter">
                <span class="k">Active jobs</span>
                <span class="n">{{ $counts['active'] }}</span>
                <span class="s">{{ $inProduction }} in production</span>
            </div>
            <div class="counter">
                <span class="k">Ready to collect</span>
                <span class="n">{{ $counts['ready'] }}</span>
                <span class="s">{{ $readyShop ?? 'Nothing at the counter' }}</span>
            </div>
            <div class="counter">
                <span class="k">Needs your action</span>
                <span class="n">{{ $needsProof ? 1 : 0 }}</span>
                <span class="s {{ $needsProof ? 'warn' : '' }}">{{ $needsProof ? 'Proof approval' : 'Nothing waiting on you' }}</span>
            </div>
        </div>

        @if ($needsProof)
            <div class="jobs-alert">
                <span class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 8v5"/><circle cx="12" cy="16.5" r=".6" fill="currentColor"/><path d="M10.3 3.9L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                </span>
                <div>
                    <b>Proof ready for approval — #{{ $needsProof->reference }}</b>
                    <div class="meta">
                        {{ $needsProof->shop->name }} · {{ $needsProof->titleLine() }} — approve within 12 hrs or the job is paused.
                    </div>
                </div>
                <a class="btn btn-primary btn-sm" style="background:var(--magenta);border-color:var(--magenta)" href="{{ route('jobs.show', $needsProof) }}">
                    Review proof
                </a>
            </div>
        @endif

        <div class="jobs-bar">
            <div class="jobs-tabs">
                @foreach ($tabs as [$key, $label, $count])
                    <a class="jobs-tab {{ $filter === $key ? 'on' : '' }}"
                       href="{{ route('account', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $q ?: null])) }}">
                        {{ $label }} <em>{{ $count }}</em>
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('account') }}" class="field" style="width:250px">
                @if ($filter !== 'all')<input type="hidden" name="status" value="{{ $filter }}">@endif
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search your jobs">
            </form>
        </div>

        @forelse ($jobs as $job)
            <article class="card job-row">
                <span class="job-thumb" style="background:{{ $job->shop->coverGradient() }}"></span>

                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                        <b style="font-size:13.5px;letter-spacing:-.01em">{{ $job->titleLine() }}</b>
                        <span class="status st-{{ $job->statusTone() }}"><i></i>{{ $job->statusLabel() }}</span>
                    </div>
                    <div class="small" style="margin-top:4px">
                        {{ $job->shop->name }} · {{ $job->rowMeta() }}
                    </div>
                </div>

                <div style="text-align:right;flex:none;min-width:96px">
                    <div style="font-size:14px;font-weight:600;letter-spacing:-.02em">{{ $peso($job->total) }}</div>
                    <div class="tiny" style="margin-top:3px">{{ $job->nextStepLine() }}</div>
                </div>

                @if ($job->status === 'awaiting_proof')
                    <a class="btn btn-sm" style="flex:none;background:var(--magenta);border-color:var(--magenta);color:#fff" href="{{ route('jobs.show', $job) }}">Review proof</a>
                @elseif ($job->status === 'collected')
                    <a class="btn btn-ghost btn-sm" style="flex:none" href="{{ route('shops.builder', [$job->shop, 'service_id' => $job->spec['service_id'] ?? null]) }}">Reorder</a>
                @else
                    <a class="btn btn-ghost btn-sm" style="flex:none" href="{{ route('jobs.show', $job) }}">View</a>
                @endif
            </article>
        @empty
            <div class="card" style="padding:48px 24px;text-align:center">
                <b>{{ $q !== '' ? 'Nothing matches “'.$q.'”.' : 'No jobs here yet.' }}</b>
                <p class="small" style="margin-top:6px">
                    {{ $q !== '' ? 'Try a shop name, a service or a job number.' : 'Find a shop, set your size, and see the exact price before you order.' }}
                </p>
                <a class="btn btn-primary btn-sm" style="margin-top:16px" href="{{ route('shops.index') }}">Browse shops</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
