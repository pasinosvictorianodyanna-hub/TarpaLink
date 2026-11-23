@extends('layouts.app')

@section('title', 'Receipt '.$job->reference.' — TarpaLink')

@section('content')
@php
    $peso = fn ($cents) => '₱'.number_format($cents / 100, 2);
@endphp

<div class="app">
    <div class="app-body" style="max-width:720px">
        <div class="crumbs">
            <a href="{{ route('account') }}">My jobs</a> <span>/</span>
            <a href="{{ route('jobs.show', $job) }}">#{{ $job->reference }}</a> <span>/</span> Receipt
        </div>

        <div class="card" style="padding:34px 36px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px">
                <div>
                    <b style="font-size:15px;letter-spacing:-.02em">{{ $job->shop->name }}</b>
                    <div class="small" style="margin-top:3px">{{ $job->shop->address }}, {{ $job->shop->city }}</div>
                    <div class="small">{{ $job->shop->phone }}</div>
                </div>
                <div style="text-align:right">
                    <div class="k" style="font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600">Receipt</div>
                    <div style="font-family:var(--serif);font-size:22px;letter-spacing:-.02em;margin-top:4px">{{ $job->reference }}</div>
                    <div class="tiny">{{ $job->created_at->format('d M Y · g:i A') }}</div>
                </div>
            </div>

            <div style="display:flex;gap:40px;margin-top:26px;padding-top:18px;border-top:1px solid var(--line)">
                <div>
                    <div class="tiny">Billed to</div>
                    <div style="font-size:13px;font-weight:600;margin-top:4px">{{ $job->user?->name }}</div>
                    <div class="small">{{ $job->user?->email }}</div>
                </div>
                <div>
                    <div class="tiny">Job</div>
                    <div style="font-size:13px;font-weight:600;margin-top:4px">{{ $job->titleLine() }}</div>
                    <div class="small">{{ $job->specSummary() }}</div>
                </div>
            </div>

            <table class="tbl" style="margin-top:24px;border-top:1px solid var(--line)">
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

            <div style="display:flex;justify-content:space-between;gap:20px;margin-top:22px;padding-top:16px;border-top:1px solid var(--line)">
                <div class="small">
                    @if ($job->payment)
                        Paid with {{ $job->payment->provider === 'paymongo' ? 'PayMongo' : ucfirst($job->payment->provider) }}
                        — {{ ucfirst($job->payment->status) }}{{ $job->payment->paid_at ? ' on '.$job->payment->paid_at->format('d M Y') : '' }}.
                    @else
                        Cash at pickup — pay at the counter when you collect.
                    @endif
                </div>
                <div class="tiny" style="text-align:right">
                    Price locked at order time
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:16px">
            <a class="btn btn-ghost btn-sm" href="{{ route('jobs.show', $job) }}">Back to job</a>
            <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Print</button>
        </div>
    </div>
</div>
@endsection
