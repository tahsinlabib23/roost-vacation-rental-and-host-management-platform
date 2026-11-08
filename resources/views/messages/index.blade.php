@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/messages.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/messages.js') }}" defer></script>
@endpush

@section('title', 'Messages')

@section('content')
    <div
        class="messages-page{{ $selectedConversation ? ' has-selected-thread' : '' }}"
        data-messages-app
        data-user-id="{{ $user->id }}"
        data-inbox-url="{{ route('messages.inbox') }}"
        @if ($selectedConversation)
            data-conversation-id="{{ $selectedConversation['id'] }}"
            data-items-url="{{ route('messages.items', $selectedConversation['id']) }}"
            data-read-url="{{ route('messages.read', $selectedConversation['id']) }}"
        @endif
    >
        <header class="page-header messages-header">
            <div>
                <p class="eyebrow">Guest-host conversations</p>
                <h1>Messages</h1>
                <p class="page-intro">{{ $isHost ? 'Keep guest questions and property details together.' : 'Find a host and keep every stay conversation in one place.' }}</p>
            </div>
            <div class="header-user">
                <span class="header-greeting">{{ $isHost ? 'Host inbox' : 'Guest inbox' }}</span>
                <span class="avatar avatar-light" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
            </div>
        </header>

        @if ($errors->any())
            <div class="notice notice-error messages-errors" role="alert" tabindex="-1" data-error-summary>
                <strong>Please check the highlighted information.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="messages-loading-state" role="status" hidden data-messages-loading>Loading messages…</div>
        <div class="notice notice-error" role="alert" hidden data-messages-failure>Messages could not be loaded. Please try again.</div>

        @unless ($isHost)
            @include('messages.partials.search')

            @if ($searchResults !== null)
                @include('messages.partials.search-results', ['results' => $searchResults])
            @endif
        @endunless

        <div class="messages-workspace">
            <aside class="messages-inbox" aria-label="Conversations and guest search">
                @include('messages.partials.conversation-list', [
                    'conversations' => $inbox,
                    'selectedId' => $selectedConversation['id'] ?? null,
                ])
            </aside>

            <section class="messages-thread" aria-label="Selected conversation">
                @include('messages.partials.thread', [
                    'conversation' => $selectedConversation,
                    'history' => $history,
                ])
            </section>
        </div>
    </div>
@endsection
