<section class="message-search-results" aria-labelledby="message-search-results-heading">
    <div class="messages-section-heading">
        <h2 id="message-search-results-heading">Search results</h2>
        <span>{{ $results->total() }} found</span>
    </div>

    @if ($results->isEmpty())
        <div class="messages-empty-state">
            <h3>No matching host and property</h3>
            <p>Check both names and search again.</p>
        </div>
    @else
        <div class="search-result-list">
            @foreach ($results as $result)
                @php
                    $isSubmittedProperty = (int) old('property_id') === $result['property']['id'];
                @endphp
                <details class="message-search-result" @if ($isSubmittedProperty) open @endif>
                    <summary>
                        <span>
                            <strong>{{ $result['property']['title'] }}</strong>
                            <small>{{ $result['property']['location'] }}</small>
                        </span>
                        <span class="search-result-host">Hosted by {{ $result['host']['name'] }}</span>
                    </summary>

                    <form method="POST" action="{{ route('messages.conversations.store') }}" class="first-message-form">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $result['property']['id'] }}">
                        <input type="hidden" name="client_message_id" value="{{ $isSubmittedProperty ? old('client_message_id') : (string) \Illuminate\Support\Str::uuid() }}">

                        <div class="form-field">
                            <label for="first-message-{{ $result['property']['id'] }}">Your first message</label>
                            <textarea id="first-message-{{ $result['property']['id'] }}" name="body" rows="4" required maxlength="2000" placeholder="Ask about the property or your stay…">{{ $isSubmittedProperty ? old('body') : '' }}</textarea>
                        </div>

                        <button class="primary-button" type="submit">Send first message</button>
                    </form>
                </details>
            @endforeach
        </div>

        @if ($results->hasPages())
            <nav class="messages-pagination" aria-label="Search result pages">
                @if ($results->onFirstPage())<span>Previous</span>@else<a href="{{ $results->previousPageUrl() }}">Previous</a>@endif
                <span>Page {{ $results->currentPage() }} of {{ $results->lastPage() }}</span>
                @if ($results->hasMorePages())<a href="{{ $results->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
            </nav>
        @endif
    @endif
</section>
