<article class="message-row{{ $message['is_mine'] ? ' is-mine' : ' is-theirs' }}" data-message-id="{{ $message['id'] }}">
    <div class="message-bubble">
        <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $message['body'] }}</p>
        <footer>
            <time datetime="{{ $message['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('M j, g:i A') }}</time>
            @if ($message['is_mine'] && $message['id'] <= ($history['partner_read_through_id'] ?? 0))
                <span>Read</span>
            @endif
        </footer>
    </div>
</article>
