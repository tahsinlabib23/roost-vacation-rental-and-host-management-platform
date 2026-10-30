@extends('layouts.app')

@section('title', $isHost ? 'Host overview' : 'Your stays')

@section('content')
    <div class="dashboard-content" id="overview">
        <header class="page-header">
            <div>
                <p class="eyebrow">{{ now()->format('l, F j, Y') }}</p>
                <h1>{{ $isHost ? 'Good to see you, '.str($user->name)->before(' ').'.' : 'Your stays, all together.' }}</h1>
                <p class="page-intro">{{ $isHost ? 'Here’s what’s happening across your places.' : 'Your plans and reservations, at a glance.' }}</p>
            </div>
            <div class="header-user">
                <span class="header-greeting">{{ $isHost ? 'Hosting space' : 'Guest space' }}</span>
                <span class="avatar avatar-light" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
            </div>
        </header>

        @if (session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="welcome-banner" aria-label="{{ $isHost ? 'Hosting overview' : 'Stay overview' }}">
            <div class="banner-copy">
                <span class="banner-overline"><span class="banner-dot" aria-hidden="true"></span>{{ $isHost ? 'Your week, at a glance' : 'Your next chapter' }}</span>
                <h2>{{ $isHost ? 'A little less juggling.' : 'Somewhere good is waiting.' }}</h2>
                <p>
                    @if ($nextArrival)
                        @if ($isHost)
                            Next check-in: {{ $nextArrival->property?->title }} on <span class="date-no-break">{{ $nextArrival->check_in->format('M j') }}</span>.
                        @else
                            Your next stay at {{ $nextArrival->property?->title }} begins <span class="date-no-break">{{ $nextArrival->check_in->format('M j') }}</span>.
                        @endif
                    @elseif ($isHost)
                        Keep every arrival, request, and place in view. Your next step is right here.
                    @else
                        Keep your booking details close and look ahead to the places you’ll go.
                    @endif
                </p>
                <a class="banner-link" href="#reservation-list">{{ $isHost ? 'Review reservations' : 'View your stays' }}<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"/></svg></a>
            </div>
            <div class="banner-photo" aria-hidden="true" style="background-image: url('{{ asset('images/roost-courtyard.webp') }}')">
                <span class="photo-label"><span class="photo-label-rule"></span>Room for a better stay</span>
            </div>
        </section>

        <section class="stats-grid" aria-label="{{ $isHost ? 'Hosting overview metrics' : 'Stay overview metrics' }}">
            @foreach ($stats as $index => $stat)
                <article class="stat-card stat-{{ $stat['tone'] }}">
                    <div class="stat-topline">
                        <span class="stat-label">{{ $stat['label'] }}</span>
                        <span class="stat-symbol" aria-hidden="true">
                            @if ($index === 0)
                                <svg viewBox="0 0 24 24"><path d="m3.5 10 8.5-6 8.5 6v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V10Z"/><path d="M9 20.5v-7h6v7"/></svg>
                            @elseif ($index === 1)
                                <svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M7.5 3.5v4M16.5 3.5v4M3.5 10h17"/></svg>
                            @elseif ($index === 2)
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/></svg>
                            @else
                                <span class="currency-mark">৳</span>
                            @endif
                        </span>
                    </div>
                    <strong class="stat-value">{{ $stat['value'] }}</strong>
                    <span class="stat-note">{{ $stat['note'] }}</span>
                </article>
            @endforeach
        </section>

        <section class="section-block reservations-section" id="reservation-list" aria-labelledby="reservations-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">{{ $isHost ? 'Keep things moving' : 'Your itinerary' }}</p>
                    <h2 id="reservations-heading">{{ $isHost ? 'Recent reservations' : 'Your reservations' }}</h2>
                </div>
                <span class="section-caption">{{ $reservations->count() }} shown</span>
            </div>

            <div class="table-shell">
                @if ($reservations->isEmpty())
                    <div class="empty-state">
                        <span class="empty-symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3.5 10 8.5-6 8.5 6v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V10Z"/><path d="M9 20.5v-7h6v7"/></svg></span>
                        <h3>{{ $isHost ? 'Your first reservation will show up here.' : 'Your next stay will find a home here.' }}</h3>
                        <p>{{ $isHost ? 'When a guest books one of your places, you’ll see their dates and details here.' : 'Once you make a booking, you can keep the dates and details close.' }}</p>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="reservation-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $isHost ? 'Guest' : 'Place' }}</th>
                                    <th scope="col">Dates</th>
                                    @if ($isHost)<th scope="col">Place</th>@endif
                                    <th scope="col">Total</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reservations as $reservation)
                                    <tr>
                                        <td data-label="{{ $isHost ? 'Guest' : 'Place' }}">
                                            <div class="person-cell">
                                                <span class="avatar avatar-table" aria-hidden="true">{{ mb_substr($isHost ? ($reservation->guest?->name ?? 'G') : ($reservation->property?->title ?? 'R'), 0, 1) }}</span>
                                                <span>
                                                    <strong>{{ $isHost ? ($reservation->guest?->name ?? 'Guest') : ($reservation->property?->title ?? 'Stay') }}</strong>
                                                    <small>{{ $isHost ? ($reservation->guest?->email ?? 'Guest account') : ($reservation->property?->location ?? 'Location pending') }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="Dates"><span class="date-range">{{ $reservation->check_in->format('M j') }} <span aria-hidden="true">–</span> {{ $reservation->check_out->format('M j, Y') }}</span></td>
                                        @if ($isHost)<td data-label="Place" class="property-cell">{{ $reservation->property?->title ?? 'Place removed' }}</td>@endif
                                        <td data-label="Total" class="amount-cell">৳{{ number_format((float) $reservation->total_amount) }}</td>
                                        <td data-label="Status"><span class="status-pill status-{{ str($reservation->status)->slug() }}">{{ ucfirst($reservation->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        @if ($isHost)
            <section class="section-block properties-section" id="properties" aria-labelledby="properties-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">The places you share</p>
                        <h2 id="properties-heading">Your places</h2>
                    </div>
                    <span class="section-caption">{{ $properties->count() }} recent</span>
                </div>

                @if ($properties->isEmpty())
                    <div class="empty-state property-empty">
                        <span class="empty-symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3.5 10 8.5-6 8.5 6v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V10Z"/><path d="M9 20.5v-7h6v7"/></svg></span>
                        <h3>Start with a place you love.</h3>
                        <p>Your listings will live here, with their current status and nightly rate.</p>
                    </div>
                @else
                    <div class="property-grid">
                        @foreach ($properties as $property)
                            <article class="property-card">
                                <div class="property-art">
                                    @if ($property->cover_image_url)
                                        <img src="{{ $property->cover_image_url }}" alt="" loading="lazy">
                                    @else
                                        <span class="property-art-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3.5 10 8.5-6 8.5 6v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V10Z"/><path d="M9 20.5v-7h6v7"/></svg></span>
                                    @endif
                                    <span class="status-pill status-{{ str($property->status)->slug() }}">{{ ucfirst($property->status) }}</span>
                                </div>
                                <div class="property-details">
                                    <h3>{{ $property->title }}</h3>
                                    <p>{{ $property->location }}</p>
                                    <div class="property-meta"><strong>৳{{ number_format((float) $property->nightly_rate) }}</strong><span>per night</span><span class="guest-capacity">{{ $property->guest_capacity }} guests</span></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
