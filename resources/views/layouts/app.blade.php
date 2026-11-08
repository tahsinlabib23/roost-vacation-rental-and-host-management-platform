<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#35422d">
    <title>@yield('title', 'Dashboard') · Roost</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/roost-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="dashboard-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="workspace">
        <aside class="sidebar">
            @include('components.brand', ['href' => route('dashboard'), 'variant' => 'sidebar-brand'])

            <div class="sidebar-context"><span class="context-label">Your space</span><span class="context-name">{{ $isHost ? 'Hosting' : 'Travel' }}</span></div>

            <nav class="side-nav" aria-label="Main navigation">
                <a class="nav-link{{ request()->routeIs('dashboard') ? ' is-active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    <span>Overview</span>
                </a>
                @if ($isHost)
                    <a class="nav-link" href="{{ route('dashboard') }}#properties">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 10 8.5-6 8.5 6v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V10Z"/><path d="M9 20.5v-7h6v7"/></svg>
                        <span>My places</span>
                        @if ($propertyCount > 0)<span class="nav-count">{{ $propertyCount }}</span>@endif
                    </a>
                @endif
                <a class="nav-link{{ request()->routeIs('messages.*') ? ' is-active' : '' }}" href="{{ route('messages.index') }}" @if (request()->routeIs('messages.*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 10h8M8 13h5"/></svg>
                    <span>Messages</span>
                </a>
                <a class="nav-link" href="{{ route('dashboard') }}#reservation-list">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M7.5 3.5v4M16.5 3.5v4M3.5 10h17"/></svg>
                    <span>{{ $isHost ? 'Reservations' : 'My stays' }}</span>
                </a>
            </nav>

            <div class="sidebar-note"><span class="note-rule" aria-hidden="true"></span><p>{{ $isHost ? 'The small details make a stay feel like home.' : 'Good trips begin with a place that feels right.' }}</p></div>

            <div class="sidebar-bottom">
                <div class="sidebar-user">
                    <span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                    <span class="sidebar-user-copy"><strong>{{ $user->name }}</strong><small>{{ ucfirst($user->role) }} account</small></span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="signout-button" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3.5M20.5 4v16h-8"/></svg>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="main-content" id="main-content" tabindex="-1">
            @yield('content')
            <footer class="workspace-footer"><span>Roost</span><span>Make room for a better stay.</span></footer>
        </main>
    </div>
</body>
</html>
