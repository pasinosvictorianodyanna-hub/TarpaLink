@extends('layouts.app')

@section('title', 'Your shop request — TarpaLink')

@section('content')
<div class="app">
    <div class="app-body" style="max-width:680px">
        <div class="crumbs">
            <a href="{{ route('home') }}">Home</a> <span>/</span> Your shop request
        </div>

        <header style="padding:8px 0 26px">
            <span class="eyebrow">Shop account</span>
            <h1 class="page-title" style="font-size:38px;margin-top:14px">
                @if ($paused)
                    Your shop is paused
                @elseif ($application === null)
                    No shop yet
                @elseif ($application->status === 'rejected')
                    Your request was not approved
                @elseif ($application->status === 'approved')
                    Approved
                @else
                    Waiting for approval
                @endif
            </h1>
        </header>

        <div class="card" style="padding:26px 24px">
            @if ($paused)
                <p class="small" style="margin:0 0 12px;line-height:1.7">
                    <b>{{ $shop->name }}</b> is not trading right now, so the console is closed.
                    An administrator can reopen it — nothing has been deleted and no orders were lost.
                </p>
            @elseif ($application === null)
                <p class="small" style="margin:0 0 16px;line-height:1.7">
                    This account is not attached to a shop. If you are opening one, send us the
                    details and an administrator will review them.
                </p>

                <a class="btn btn-primary" href="{{ route('page', 'open-a-shop') }}">Request a shop account</a>
            @elseif ($application->status === 'rejected')
                <p class="small" style="margin:0 0 14px;line-height:1.7">
                    The request for <b>{{ $application->business_name }}</b> was not approved.
                    Nothing was created, and this account is unchanged.
                </p>

                @if ($application->reason)
                    <div class="alert alert-err" role="alert" style="margin-bottom:16px">
                        {{ $application->reason }}
                    </div>
                @endif

                <p class="small" style="margin:0 0 16px;line-height:1.7">
                    You are welcome to apply again once the reason above has been addressed.
                </p>

                <a class="btn btn-primary" href="{{ route('page', 'open-a-shop') }}">Apply again</a>
            @elseif ($application->status === 'approved')
                <p class="small" style="margin:0;line-height:1.7">
                    <b>{{ $application->business_name }}</b> was approved, but the shop is not
                    reachable from this account. An administrator can put that right — nothing
                    was lost.
                </p>
            @else
                <p class="small" style="margin:0 0 16px;line-height:1.7">
                    We have the request for <b>{{ $application->business_name }}</b>, sent
                    {{ $application->created_at->diffForHumans() }}. An administrator reviews
                    every request before a shop goes live, usually within one business day.
                </p>

                <p class="small" style="margin:0 0 18px;line-height:1.7">
                    There is nothing to do until then. Sign in again later — if it has been
                    approved, this page sends you straight to the console.
                </p>

                <div class="card" style="padding:16px 18px;background:var(--surface-2, #F7F6F2)">
                    <p class="tiny" style="margin:0;line-height:1.7">
                        Signed in as <b>{{ $signedInAs }}</b>. Keep this account — the shop is
                        attached to it when it is approved.
                    </p>
                </div>
            @endif
        </div>

        <p class="tiny" style="margin:18px 0 0;line-height:1.7">
            <a href="{{ route('account') }}">Your account</a>
            <span style="opacity:.4">·</span>
            <a href="{{ route('home') }}">Back to TarpaLink</a>
        </p>
    </div>
</div>
@endsection
