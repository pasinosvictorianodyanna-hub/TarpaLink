@extends('layouts.app')

@section('title', 'Job '.$job->reference.' — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
    $proof = $job->latestProof();
@endphp

<div class="app">
    <div class="app-body">
        <div class="crumbs">
            <a href="{{ route('shops.index') }}">Print shops</a> <span>/</span> Job {{ $job->reference }}
        </div>

        <div class="page-head">
            <div>
                <h3 class="h3">{{ $job->service->name }} · {{ $job->shop->name }}</h3>
                <p>Job {{ $job->reference }} · placed {{ $job->created_at->format('d M Y') }}</p>
            </div>
            <span class="status st-{{ $job->statusTone() }}"><i></i>{{ $job->statusLabel() }}</span>
        </div>

        @if ($job->status === 'awaiting_proof')
            <div class="card" style="padding:18px 20px;border-left:3px solid var(--magenta);margin-bottom:18px">
                <b style="font-size:13.5px">Your proof is waiting for approval</b>
                <p class="small" style="margin-top:4px">Nothing goes to press until you approve. Check the logo, spelling and size.</p>
                <div style="display:flex;gap:8px;margin-top:12px">
                    <button class="btn btn-primary btn-sm" type="button">Approve proof</button>
                    <button class="btn btn-ghost btn-sm" type="button">Request a change</button>
                </div>
            </div>
        @endif

        <div class="track-grid">
            <div>
                <div class="card" style="padding:8px 22px">
                    @foreach ($job->timeline() as $step)
                        <div class="timeline-step {{ $step['done'] ? 'done' : '' }} {{ $step['current'] ? 'current' : '' }}">
                            <span class="timeline-dot">{{ $step['done'] ? '✓' : '' }}</span>
                            <div>
                                <b style="font-size:13px">{{ $step['label'] }}</b>
                                @if ($step['current'])
                                    <div class="tiny" style="margin-top:2px">In progress</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card" style="padding:0;margin-top:16px;overflow:hidden">
                    <div style="padding:18px 24px 6px"><h4 style="font-size:15px;font-weight:600">Job details</h4></div>
                    <table class="tbl">
                        <tbody>
                            @foreach ($job->lines as $line)
                                <tr>
                                    <td>{{ $line->label }}</td>
                                    <td class="r" style="color:var(--muted)">× {{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                                    <td class="r" style="font-weight:600">{{ $peso($line->amount) }}</td>
                                </tr>
                            @endforeach
                            @if ($job->discount > 0)
                                <tr>
                                    <td colspan="2" style="color:var(--green)">Bulk tier discount</td>
                                    <td class="r" style="color:var(--green);font-weight:600">−{{ $peso($job->discount) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="2"><b>Total</b></td>
                                <td class="r"><b>{{ $peso($job->total) }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="card sticky-card">
                    <div class="tiny">Pickup at</div>
                    <b style="display:block;font-size:14px;margin-top:4px">{{ $job->shop->name }}</b>
                    <div class="small" style="margin-top:2px">{{ $job->shop->address }}</div>
                    <div class="small" style="margin-top:10px">
                        Ready by
                        <b>{{ $job->due_at?->format('D d M, g:i A') ?? '—' }}</b>
                    </div>
                    <div class="tiny" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line-2)">
                        Matrix v{{ $job->matrix_version }} · price locked at order time
                    </div>
                </div>

                <div class="card" style="padding:18px;margin-top:14px">
                    <div class="tiny" style="margin-bottom:8px">Payment</div>
                    @if ($job->payment)
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px">
                            <span style="color:var(--muted)">Method</span><b>{{ $job->payment->provider === 'paymongo' ? 'PayMongo' : ucfirst($job->payment->provider) }}</b>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px">
                            <span style="color:var(--muted)">Status</span>
                            <b>{{ ucfirst($job->payment->status) }}</b>
                        </div>
                    @else
                        <div class="small">Cash at pickup — pay at the counter when you collect.</div>
                    @endif
                </div>

                @if ($proof)
                    <div class="card" style="padding:18px;margin-top:14px">
                        <div class="tiny" style="margin-bottom:6px">Proof v{{ $proof->version }}</div>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px">
                            <span style="color:var(--muted)">Status</span>
                            <b>{{ ucfirst($proof->status) }}</b>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
