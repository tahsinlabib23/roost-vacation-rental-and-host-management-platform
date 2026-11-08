<section class="message-search" aria-labelledby="message-search-heading">
    <div class="messages-section-heading">
        <div>
            <p class="eyebrow">Start a conversation</p>
            <h2 id="message-search-heading">Find a host</h2>
        </div>
    </div>

    <p class="field-hint" id="message-search-help">Enter both the host name and property name.</p>

    <form method="GET" action="{{ route('messages.search') }}" class="message-search-form" aria-describedby="message-search-help">
        <div class="form-field">
            <label for="host_name">Host name</label>
            <input id="host_name" name="host_name" type="search" required minlength="2" maxlength="120" value="{{ old('host_name', request('host_name')) }}" autocomplete="off">
            @error('host_name')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-field">
            <label for="property_name">Property name</label>
            <input id="property_name" name="property_name" type="search" required minlength="2" maxlength="160" value="{{ old('property_name', request('property_name')) }}" autocomplete="off">
            @error('property_name')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <button class="primary-button message-search-button" type="submit">Search</button>
    </form>
</section>
