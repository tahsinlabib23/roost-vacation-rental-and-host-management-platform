@if (! $conversation)
    <div class="messages-no-selection">
        <span class="empty-symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 10h8M8 13h5"/></svg></span>
        <h2>Select a conversation</h2>
        <p>Choose a conversation from your inbox to read and reply.</p>
    </div>
@else
    <a class="messages-back-link" href="{{ route('messages.index') }}">← Back to conversations</a>

    <header class="thread-header">
        <span class="avatar" aria-hidden="true">{{ mb_substr($conversation['counterpart']['name'], 0, 1) }}</span>
        <span>
            <h2>{{ $conversation['counterpart']['name'] }}</h2>
            <p>{{ $conversation['property']['title'] }}</p>
        </span>
    </header>

    <div class="message-history" aria-live="polite">
        @if ($history['has_more'])
            <a class="load-older-link" href="{{ route('messages.items', ['conversation' => $conversation['id'], 'before_id' => $history['before_id']]) }}">Load older messages</a>
        @endif

        @forelse ($history['data'] as $message)
            @include('messages.partials.message-bubble', ['message' => $message])
        @empty
            <div class="messages-empty-state">
                <h3>No messages in this conversation</h3>
                <p>Send a message to begin.</p>
            </div>
        @endforelse
    </div>

    @php
        $lastDisplayedIncoming = collect($history['data'])->where('is_mine', false)->max('id');
    @endphp

    @if ($lastDisplayedIncoming)
        <form method="POST" action="{{ route('messages.read', $conversation['id']) }}" class="mark-read-form">
            @csrf
            <input type="hidden" name="through_message_id" value="{{ $lastDisplayedIncoming }}">
            <button type="submit" class="text-button">Mark displayed messages as read</button>
        </form>
    @endif

    @include('messages.partials.composer', ['conversationId' => $conversation['id']])
@endif
