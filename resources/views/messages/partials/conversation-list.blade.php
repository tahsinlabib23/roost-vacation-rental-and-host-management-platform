<section class="conversation-list-section" aria-labelledby="conversation-list-heading">
    <div class="messages-section-heading">
        <h2 id="conversation-list-heading">Your conversations</h2>
        <span>{{ $conversations->total() }}</span>
    </div>

    @if ($conversations->isEmpty())
        <div class="messages-empty-state">
            <h3>No conversations yet</h3>
            <p>{{ $isHost ? 'Guest messages will appear here.' : 'Search for a host and send the first message.' }}</p>
        </div>
    @else
        <div class="conversation-list">
            @foreach ($conversations as $conversation)
                <a class="conversation-list-item{{ $selectedId === $conversation['id'] ? ' is-selected' : '' }}" href="{{ route('messages.show', $conversation['id']) }}" @if ($selectedId === $conversation['id']) aria-current="page" @endif>
                    <span class="avatar" aria-hidden="true">{{ mb_substr($conversation['counterpart']['name'], 0, 1) }}</span>
                    <span class="conversation-preview">
                        <span class="conversation-preview-topline">
                            <strong>{{ $conversation['counterpart']['name'] }}</strong>
                            @if ($conversation['unread_count'] > 0)<span class="conversation-unread">{{ $conversation['unread_count'] }}</span>@endif
                        </span>
                        <small>{{ $conversation['property']['title'] }}</small>
                        <span>{{ \Illuminate\Support\Str::limit($conversation['latest_message']['body'] ?? 'Conversation started', 72) }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        @if ($conversations->hasPages())
            <nav class="messages-pagination" aria-label="Conversation pages">
                @if ($conversations->onFirstPage())<span>Previous</span>@else<a href="{{ $conversations->previousPageUrl() }}">Previous</a>@endif
                <span>Page {{ $conversations->currentPage() }} of {{ $conversations->lastPage() }}</span>
                @if ($conversations->hasMorePages())<a href="{{ $conversations->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
            </nav>
        @endif
    @endif
</section>
