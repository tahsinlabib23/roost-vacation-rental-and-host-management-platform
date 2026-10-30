@extends('layouts.auth')

@section('title', 'Create your account')

@section('content')
    <div class="form-heading">
        <p class="eyebrow">A good place to begin</p>
        <h1>Join Roost.</h1>
        <p>Create an account to host a place or plan a stay.</p>
    </div>

    @if ($errors->any())
        <div class="error-summary" role="alert" tabindex="-1" data-error-summary>
            <strong>There’s a little more to fill in.</strong>
            <p>Review the highlighted fields and try again.</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="auth-form register-form" method="POST" action="{{ route('register.store') }}">
        @csrf

        <div class="field">
            <label for="name">Your name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus placeholder="Name you go by" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
        </div>

        <fieldset class="role-picker" @error('role') aria-describedby="role-error" @enderror>
            <legend>I’m here to…</legend>
            <label class="role-option" for="role-host">
                <input id="role-host" type="radio" name="role" value="host" @checked(old('role', 'host') === 'host')>
                <span class="role-radio" aria-hidden="true"></span>
                <span><strong>Host a place</strong><small>Manage your rentals and guest stays</small></span>
            </label>
            <label class="role-option" for="role-guest">
                <input id="role-guest" type="radio" name="role" value="guest" @checked(old('role') === 'guest')>
                <span class="role-radio" aria-hidden="true"></span>
                <span><strong>Plan a stay</strong><small>Keep your trips and bookings together</small></span>
            </label>
            @error('role')<p class="field-error" id="role-error">{{ $message }}</p>@enderror
        </fieldset>

        <div class="field">
            <label for="password">Create a password</label>
            <div class="password-field">
                <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 characters" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
            </div>
            @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Enter it once more">
        </div>

        <button class="button button-primary button-wide" type="submit"><span>Create your account</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"/></svg></button>
    </form>

    <p class="form-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    <p class="form-privacy">By joining, you agree to use Roost respectfully and keep your account details secure.</p>
@endsection
