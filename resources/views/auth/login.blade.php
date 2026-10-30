@extends('layouts.auth')

@section('title', 'Welcome back')

@section('content')
    <div class="form-heading">
        <p class="eyebrow">Your hosting space</p>
        <h1>Welcome back.</h1>
        <p>Sign in to pick up where you left off.</p>
    </div>

    @if (session('status'))
        <div class="notice notice-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error-summary" role="alert" tabindex="-1" data-error-summary>
            <strong>We couldn’t sign you in.</strong>
            <p>Check the details below and try again.</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="you@example.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <div class="field-label-row"><label for="password">Password</label></div>
            <div class="password-field">
                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
            </div>
            @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
        </div>

        <label class="remember-option">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span class="checkmark" aria-hidden="true"></span>
            <span>Keep me signed in</span>
        </label>

        <button class="button button-primary button-wide" type="submit"><span>Sign in</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"/></svg></button>
    </form>

    <p class="form-switch">New to Roost? <a href="{{ route('register') }}">Create an account</a></p>
    <p class="form-privacy">Your account keeps your stays and hosting details in one private place.</p>
@endsection
