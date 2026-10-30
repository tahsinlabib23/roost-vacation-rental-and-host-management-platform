<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#35422d">
    <title>@yield('title', 'Sign in') · Roost</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/roost-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="auth-page">
    <a class="skip-link" href="#main-content">Skip to form</a>
    <main class="auth-shell" id="main-content" tabindex="-1">
        <section class="auth-main" aria-label="Account access">
            @include('components.brand', ['href' => route('home'), 'variant' => 'auth-brand'])

            <div class="auth-form-wrap">
                @yield('content')
            </div>

            <p class="auth-footnote">A better stay begins with a little care.</p>
        </section>

        <aside class="auth-story" aria-label="Roost for thoughtful stays">
            <img class="story-photo" src="{{ asset('images/roost-courtyard.webp') }}" alt="A quiet guesthouse courtyard with palms, a reflecting pool, and a teak entryway">
            <div class="story-shade" aria-hidden="true"></div>
            <div class="story-topline"><span class="story-sun" aria-hidden="true"></span><span>Space to host well</span></div>
            <div class="story-copy">
                <p class="story-kicker">Your Roost, in good order</p>
                <h2>Make room for<br>a better stay.</h2>
                <p class="story-description">One considered place to look after your listings, welcome your guests, and keep every detail close.</p>
            </div>
            <p class="story-caption"><span class="caption-rule" aria-hidden="true"></span><span><strong>Good stays, made simple</strong><small>Built around the people who host</small></span></p>
        </aside>
    </main>
</body>
</html>
