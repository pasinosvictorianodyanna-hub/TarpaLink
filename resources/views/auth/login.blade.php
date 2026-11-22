@extends('layouts.app')

@section('title', 'Sign in — TarpaLink')

@section('content')
<div class="wrap-sm" style="padding:70px 18px 90px">
    <div class="card" style="padding:32px 30px;max-width:420px;margin:0 auto">
        <h1 class="h3" style="margin-bottom:6px">Sign in</h1>
        <p class="small" style="margin-bottom:22px">Track your jobs and approve proofs.</p>

        @if ($errors->any())
            <div class="alert" role="alert" style="background:var(--tint-m);border-color:#F5C4B3;color:var(--red);margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="alert" role="status" style="background:#F1F7F1;border-color:#C9E2C9;color:#1F5130;margin-bottom:16px">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" autocomplete="email" value="{{ old('email', 'jomar@tarpalink.ph') }}" required autofocus>
            </label>
            <label class="field" style="margin-top:12px">
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" value="password" required>
            </label>
            <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:12.5px;color:var(--muted)">
                <input type="checkbox" name="remember"> Remember me
            </label>

            <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:18px" type="submit">
                Sign in
            </button>
        </form>

        <div class="tiny" style="margin-top:14px;text-align:center">
            <a href="{{ route('password.request') }}" style="text-decoration:underline;text-underline-offset:2px;color:var(--muted)">Forgot your password?</a>
        </div>

        <div class="tiny" style="margin-top:16px;text-align:center">
            New here? <a href="{{ route('register') }}" style="text-decoration:underline;text-underline-offset:2px;color:var(--ink)">Create an account</a>
        </div>

        <div class="tiny" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line-2);line-height:1.7">
            Seeded demo accounts (password: <b>password</b>)<br>
            Customer — jomar@tarpalink.ph<br>
            Shop owner — kevin@inkwell.ph
        </div>
    </div>
</div>
@endsection
