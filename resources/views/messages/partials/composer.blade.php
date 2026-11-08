<form method="POST" action="{{ route('messages.store', $conversationId) }}" class="message-composer">
    @csrf
    <input type="hidden" name="client_message_id" value="{{ old('client_message_id', (string) \Illuminate\Support\Str::uuid()) }}">

    <div class="form-field">
        <label for="message-body">Message</label>
        <textarea id="message-body" name="body" rows="3" required maxlength="2000" placeholder="Write a message…">{{ old('body') }}</textarea>
        @error('body')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <button class="primary-button" type="submit">Send message</button>
</form>
