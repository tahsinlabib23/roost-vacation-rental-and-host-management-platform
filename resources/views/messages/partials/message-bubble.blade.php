<article class="message-row{{ $message['is_mine'] ? ' is-mine' : ' is-theirs' }}" data-message-id="{{ $message['id'] }}" data-message-direction="{{ $message['is_mine'] ? 'outgoing' : 'incoming' }}">
    <div class="message-bubble">
        <p>{{ $message['body'] }}</p>
        <footer>
            <time datetime="{{ $message['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('M j, g:i A') }}</time>
            @if ($message['is_mine'])
                <span data-message-receipt @if ($message['id'] > ($history['partner_read_through_id'] ?? 0)) hidden @endif>Read</span>
            @endif
        </footer>
    </div>
</article>
