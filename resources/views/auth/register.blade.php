@extends('layouts.app')

@section('title', 'Create an account — TarpaLink')

@section('content')
<div class="wrap-sm" style="padding:70px 18px 90px">
    <div class="card" style="padding:32px 30px;max-width:420px;margin:0 auto">
        <h1 class="h3" style="margin-bottom:6px">Create an account</h1>
        <p class="small" style="margin-bottom:22px">Track your jobs, save your details and approve proofs in one place.</p>

        @if ($errors->any())
            <div class="alert" role="alert" style="background:var(--tint-m);border-color:#F5C4B3;color:var(--red);margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <label class="field">
                <span>Full name</span>
                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            </label>
            <label class="field" style="margin-top:12px">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </label>
            <label class="field" style="margin-top:12px">
                <span>Password</span>
                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>
            <label class="field" style="margin-top:12px">
                <span>Confirm password</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </label>

            <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px" type="submit">
                Create account
            </button>
        </form>

        <div class="tiny" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line-2);line-height:1.7">
            Already have an account? <a href="{{ route('login') }}" style="text-decoration:underline;text-underline-offset:2px">Sign in</a>
        </div>
    </div>
</div>
@endsection
