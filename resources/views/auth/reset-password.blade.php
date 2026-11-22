@extends('layouts.app')

@section('title', 'Set a new password — TarpaLink')

@section('content')
<div class="wrap-sm" style="padding:70px 18px 90px">
    <div class="card" style="padding:32px 30px;max-width:420px;margin:0 auto">
        <h1 class="h3" style="margin-bottom:6px">Set a new password</h1>
        <p class="small" style="margin-bottom:22px">Choose something you have not used on this account before.</p>

        @if ($errors->any())
            <div class="alert" role="alert" style="background:var(--tint-m);border-color:#F5C4B3;color:var(--red);margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="field">
                <span>Email</span>
                <input type="email" name="email" autocomplete="email" value="{{ old('email', $email) }}" required readonly>
            </label>

            <label class="field" style="margin-top:12px">
                <span>New password</span>
                <input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
            </label>

            <label class="field" style="margin-top:12px">
                <span>Confirm new password</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </label>

            <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:18px" type="submit">
                Change password
            </button>
        </form>

        <div class="tiny" style="margin-top:16px;text-align:center">
            <a href="{{ route('password.request') }}" style="text-decoration:underline;text-underline-offset:2px;color:var(--ink)">Send me a new link</a>
        </div>
    </div>
</div>
@endsection
