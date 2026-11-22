@extends('layouts.app')

@section('title', 'Reset your password — TarpaLink')

@section('content')
<div class="wrap-sm" style="padding:70px 18px 90px">
    <div class="card" style="padding:32px 30px;max-width:420px;margin:0 auto">
        <h1 class="h3" style="margin-bottom:6px">Forgot your password?</h1>
        <p class="small" style="margin-bottom:22px">
            Enter the email on your account and we will send you a link to set a new password.
        </p>

        @if (session('status'))
            <div class="alert" role="status" style="background:#F1F7F1;border-color:#C9E2C9;color:#1F5130;margin-bottom:16px">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert" role="alert" style="background:var(--tint-m);border-color:#F5C4B3;color:var(--red);margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required autofocus>
            </label>

            <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:18px" type="submit">
                Send reset link
            </button>
        </form>

        <div class="tiny" style="margin-top:16px;text-align:center">
            Remembered it? <a href="{{ route('login') }}" style="text-decoration:underline;text-underline-offset:2px;color:var(--ink)">Back to sign in</a>
        </div>
    </div>
</div>
@endsection
