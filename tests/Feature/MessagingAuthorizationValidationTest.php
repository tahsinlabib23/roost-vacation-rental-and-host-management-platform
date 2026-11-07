<?php

namespace Tests\Feature;

use App\Http\Requests\Messaging\InboxRequest;
use App\Http\Requests\Messaging\MarkConversationReadRequest;
use App\Http\Requests\Messaging\MessageHistoryRequest;
use App\Http\Requests\Messaging\SearchHostsRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingAuthorizationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->prefix('/_f6-validation')->group(function () {
            Route::get('/search', fn (SearchHostsRequest $request) => response()->json($request->validated()))
                ->middleware('throttle:messaging-search');
            Route::post('/start', fn (StartConversationRequest $request) => response()->json($request->validated()));
            Route::post('/send/{conversation}', fn (SendMessageRequest $request, Conversation $conversation) => response()->json($request->validated()))
                ->middleware('throttle:messaging-send');
            Route::post('/read/{conversation}', fn (MarkConversationReadRequest $request, Conversation $conversation) => response()->json($request->validated()));
            Route::get('/history/{conversation}', fn (MessageHistoryRequest $request, Conversation $conversation) => response()->json($request->validated()));
            Route::get('/inbox', fn (InboxRequest $request) => response()->json($request->validated()));
        });
    }

    public function test_messaging_validation_routes_require_authentication(): void
    {
        $this->getJson('/_f6-validation/search?host_name=Host&property_name=Place')->assertUnauthorized();
    }

    public function test_only_guests_can_search_and_start_conversations(): void
    {
        $host = $this->createUser('Validation Host', 'host');
        $property = $this->createProperty($host, 'Validation Place');

        $this->actingAs($host)
            ->getJson('/_f6-validation/search?host_name=Validation&property_name=Place')
            ->assertForbidden();

        $this->actingAs($host)->postJson('/_f6-validation/start', [
            'property_id' => $property->id,
            'body' => 'Hello',
            'client_message_id' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_search_trims_both_required_terms_and_rejects_client_page_size(): void
    {
        $guest = $this->createUser('Search Guest', 'guest');

        $this->actingAs($guest)
            ->getJson('/_f6-validation/search?host_name=%20Host%20&property_name=%20Place%20')
            ->assertOk()
            ->assertJson(['host_name' => 'Host', 'property_name' => 'Place']);

        $this->actingAs($guest)
            ->getJson('/_f6-validation/search?host_name=H&property_name=Place&page=0&per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['host_name', 'page', 'per_page']);
    }

    public function test_start_request_rejects_unavailable_properties_bad_messages_and_identity_injection(): void
    {
        $guest = $this->createUser('Start Guest', 'guest');
        $host = $this->createUser('Start Host', 'host');
        $draft = $this->createProperty($host, 'Draft Place', 'draft');

        $this->actingAs($guest)->postJson('/_f6-validation/start', [
            'property_id' => $draft->id,
            'body' => '   ',
            'client_message_id' => 'not-a-uuid',
            'sender_id' => $host->id,
            'host_id' => $host->id,
            'role' => 'host',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['property_id', 'body', 'client_message_id', 'sender_id', 'host_id', 'role']);
    }

    public function test_start_request_rechecks_that_property_owner_is_still_a_host(): void
    {
        $guest = $this->createUser('Owner Role Guest', 'guest');
        $invalidOwner = $this->createUser('Not Actually Host', 'guest');
        $property = $this->createProperty($invalidOwner, 'Invalid Owner Place');

        $this->actingAs($guest)->postJson('/_f6-validation/start', [
            'property_id' => $property->id,
            'body' => 'Can I message?',
            'client_message_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('property_id');
    }

    public function test_send_request_rejects_outsiders_invalid_payloads_and_owner_changes(): void
    {
        [$guest, $host, $property, $conversation] = $this->conversationFixture();
        $outsider = $this->createUser('Send Outsider', 'guest');

        $this->actingAs($outsider)->postJson("/_f6-validation/send/{$conversation->id}", [
            'body' => 'Outsider message',
            'client_message_id' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->actingAs($guest)->postJson("/_f6-validation/send/{$conversation->id}", [
            'body' => str_repeat('x', 2001),
            'client_message_id' => 'bad-uuid',
            'sender_id' => $host->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['body', 'client_message_id', 'sender_id']);

        $newHost = $this->createUser('Transferred Host', 'host');
        $property->update(['host_id' => $newHost->id]);

        $this->actingAs($guest)->postJson("/_f6-validation/send/{$conversation->id}", [
            'body' => 'Blocked after owner transfer',
            'client_message_id' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_read_request_only_accepts_a_message_from_the_selected_conversation(): void
    {
        [$guest, $host, , $conversation] = $this->conversationFixture();
        $message = $this->createMessage($conversation, $host, 'Selected thread message');
        $otherProperty = $this->createProperty($host, 'Other Validation Place');
        $otherConversation = $this->createConversation($otherProperty, $guest, $host);
        $otherMessage = $this->createMessage($otherConversation, $host, 'Other thread message');

        $this->actingAs($guest)->postJson("/_f6-validation/read/{$conversation->id}", [
            'through_message_id' => $message->id,
        ])->assertOk();

        $this->actingAs($guest)->postJson("/_f6-validation/read/{$conversation->id}", [
            'through_message_id' => $otherMessage->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('through_message_id');
    }

    public function test_history_cursors_are_nonnegative_and_mutually_exclusive(): void
    {
        [$guest, , , $conversation] = $this->conversationFixture();

        $this->actingAs($guest)
            ->getJson("/_f6-validation/history/{$conversation->id}?before_id=3&after_id=4&per_page=500")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['before_id', 'after_id', 'per_page']);

        $this->actingAs($guest)
            ->getJson("/_f6-validation/history/{$conversation->id}?before_id=-1")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('before_id');
    }

    public function test_search_rate_limit_is_enforced_server_side(): void
    {
        $guest = $this->createUser('Rate Limited Guest', 'guest');
        $this->actingAs($guest);
        $url = '/_f6-validation/search?host_name=Host&property_name=Place';

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson($url)->assertTooManyRequests();
    }

    public function test_send_rate_limit_is_enforced_server_side(): void
    {
        [$guest, , , $conversation] = $this->conversationFixture();
        $this->actingAs($guest);
        $url = "/_f6-validation/send/{$conversation->id}";

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->postJson($url, [
                'body' => 'Validated send',
                'client_message_id' => (string) Str::uuid(),
            ])->assertOk();
        }

        $this->postJson($url, [
            'body' => 'Rate limited send',
            'client_message_id' => (string) Str::uuid(),
        ])->assertTooManyRequests();
    }

    private function conversationFixture(): array
    {
        $guest = $this->createUser('Conversation Guest', 'guest');
        $host = $this->createUser('Conversation Host', 'host');
        $property = $this->createProperty($host, 'Conversation Place');
        $conversation = $this->createConversation($property, $guest, $host);

        return [$guest, $host, $property, $conversation];
    }

    private function createUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.Str::uuid().'@example.test',
            'role' => $role,
            'password' => 'Validation-password-123',
        ]);
    }

    private function createProperty(User $host, string $title, string $status = 'published'): Property
    {
        return Property::create([
            'host_id' => $host->id,
            'title' => $title,
            'location' => 'Dhaka',
            'nightly_rate' => 5000,
            'status' => $status,
            'guest_capacity' => 2,
        ]);
    }

    private function createConversation(Property $property, User $guest, User $host): Conversation
    {
        $conversation = new Conversation;
        $conversation->property()->associate($property);
        $conversation->guest()->associate($guest);
        $conversation->host()->associate($host);
        $conversation->save();

        return $conversation;
    }

    private function createMessage(Conversation $conversation, User $sender, string $body): Message
    {
        $message = new Message([
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);
        $message->save();

        return $message;
    }
}
