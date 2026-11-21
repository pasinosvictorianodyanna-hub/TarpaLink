@extends('layouts.app')

@section('title', 'Job '.$job->reference.' — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $proof = $job->latestProof();
    $steps = $job->progressSteps();
    $maps = 'https://www.google.com/maps/search/?api=1&query='.urlencode($job->shop->address.', '.$job->shop->city);
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('account') }}">My jobs</a> <span>/</span> #{{ $job->reference }}
        </div>

        <div class="card job-head">
            <div style="min-width:0">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <h1 class="job-title">{{ $job->titleLine() }}</h1>
                    <span class="status st-{{ $job->statusTone() }}"><i></i>{{ $job->statusLabel() }}</span>
                </div>
                <p class="page-sub">
                    Order #{{ $job->reference }} · placed {{ $job->created_at->format('d M, g:i A') }} · {{ $job->shop->name }}
                </p>
            </div>
            <div class="job-head-actions">
                <a class="btn btn-primary btn-sm" href="{{ route('jobs.receipt', $job) }}">View receipt</a>
            </div>
        </div>

        @if ($job->status === 'awaiting_proof')
            <div class="jobs-alert">
                <span class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 8v5"/><circle cx="12" cy="16.5" r=".6" fill="currentColor"/><path d="M10.3 3.9L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                </span>
                <div>
                    <b>Your proof is waiting for approval</b>
                    <div class="meta">Nothing goes to press until you approve. Check the logo, the spelling and the size.</div>
                </div>
            </div>
        @endif

        <div class="job-grid">
            <div class="job-col">
                <div class="card job-panel">
                    <div class="job-panel-head">
                        <h4>Progress</h4>
                        @if ($job->due_at && ! in_array($job->status, ['collected', 'cancelled'], true))
                            <span class="tiny">Est. ready {{ $job->due_at->format('D, d M · g:i A') }}</span>
                        @endif
                    </div>

                    <div class="rail">
                        @foreach ($steps as $step)
                            <div class="rail-step {{ $step['state'] }}">
                                <span class="rail-dot">
                                    @if ($step['state'] === 'done')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </span>
                                <div>
                                    <b>{{ $step['label'] }}</b>
                                    @if ($step['detail'])<div class="d">{{ $step['detail'] }}</div>@endif
                                    @if ($step['at'])<div class="t">{{ $step['at']->format('d M · g:i A') }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($proof)
                    <div class="card job-panel">
                        <div class="job-panel-head">
                            <h4>Approved proof</h4>
                            <span class="tag {{ $proof->approved_at ? 'tag-g' : 'tag-c' }}">
                                {{ $proof->approved_at ? 'Approved v'.$proof->version : 'Awaiting approval · v'.$proof->version }}
                            </span>
                        </div>
                        @if ($proof->approved_at)
                            <p class="small">
                                Version {{ $proof->version }} approved {{ $proof->approved_at->format('d M Y · g:i A') }}.
                                The artwork is locked — what goes on press is what you signed off.
                            </p>
                        @else
                            <p class="small">The shop has uploaded version {{ $proof->version }} and it is waiting on you.</p>
                        @endif
                        @if ($proof->note)
                            <p class="small" style="margin-top:8px;color:var(--ink-2)">“{{ $proof->note }}”</p>
                        @endif
                    </div>
                @endif
            </div>

            <aside class="job-side">
                <div class="card pickup">
                    <div class="k">Pickup details</div>
                    <div class="ref">{{ $job->reference }}</div>
                    <div class="tiny" style="margin-top:3px">Your order reference</div>

                    <div class="kv" style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--line)">
                        <span>Shop</span><b>{{ $job->shop->name }}</b>
                    </div>
                    <div class="kv">
                        <span>Address</span><b>{{ $job->shop->address }}, {{ $job->shop->city }}</b>
                    </div>
                    <div class="kv">
                        <span>Ready</span><b>{{ $job->due_at?->format('D, g:i A') ?? '—' }}</b>
                    </div>

                    <p class="tiny" style="margin-top:12px;line-height:1.55">
                        Give your order number at the counter. Payment is released to the shop once you collect.
                    </p>

                    <a class="btn btn-ghost btn-sm" style="width:100%;margin-top:12px" href="{{ $maps }}" target="_blank" rel="noopener">
                        Get directions
                    </a>
                </div>

                <div class="card" style="padding:16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <span class="avatar" style="background:{{ $job->shop->coverGradient() }}">
                            {{ \Illuminate\Support\Str::of($job->shop->name)->explode(' ')->filter(fn ($p) => preg_match('/^\p{L}/u', $p))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                        </span>
                        <div style="min-width:0">
                            <b style="display:block;font-size:13px">{{ $job->shop->name }}</b>
                            <span class="tiny">★ {{ number_format($job->shop->rating, 1) }} · {{ $job->shop->turnaroundLabel() }}</span>
                        </div>
                    </div>
                    <div class="kv" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line-2)">
                        <span>Payment</span>
                        <b>
                            @if ($job->payment)
                                {{ $job->payment->provider === 'paymongo' ? 'PayMongo' : ucfirst($job->payment->provider) }}
                                · {{ ucfirst($job->payment->status) }}
                            @else
                                Cash at pickup
                            @endif
                        </b>
                    </div>
                    <div class="kv">
                        <span>Total</span><b>{{ $peso($job->total) }}</b>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
