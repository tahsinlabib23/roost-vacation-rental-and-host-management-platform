<?php

namespace Tests\Feature;

use App\Exceptions\MessageConflictException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessagingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(MessagingService::class);
    }

    public function test_start_conversation_derives_participants_and_stores_first_message_atomically(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $message = $this->service->startConversation($guest, $property, 'Hello host', (string) Str::uuid());
        $conversation = $message->conversation;

        $this->assertSame($guest->id, $conversation->guest_id);
        $this->assertSame($host->id, $conversation->host_id);
        $this->assertSame($property->id, $conversation->property_id);
        $this->assertSame($guest->id, $message->sender_id);
        $this->assertSame('Hello host', $message->body);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_failed_first_message_insert_leaves_no_empty_conversation(): void
    {
        [$guest, , $property] = $this->participantsAndProperty();

        try {
            $this->service->startConversation(
                $guest,
                $property,
                str_repeat('x', 70000),
                (string) Str::uuid(),
            );
            $this->fail('The oversized database insert should fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('conversations', 0);
            $this->assertDatabaseCount('messages', 0);
        }
    }

    public function test_retry_with_same_token_conversation_and_body_returns_original_message(): void
    {
        [$guest, , $property] = $this->participantsAndProperty();
        $token = (string) Str::uuid();

        $original = $this->service->startConversation($guest, $property, 'Retry me', $token);
        $retry = $this->service->startConversation($guest, $property, 'Retry me', $token);

        $this->assertSame($original->id, $retry->id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_retry_token_with_different_body_returns_conflict(): void
    {
        [$guest, , $property] = $this->participantsAndProperty();
        $token = (string) Str::uuid();
        $this->service->startConversation($guest, $property, 'Original body', $token);

        $this->expectException(MessageConflictException::class);
        $this->service->startConversation($guest, $property, 'Changed body', $token);
    }

    public function test_retry_token_cannot_be_reused_in_another_conversation(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $secondProperty = $this->createProperty($host, 'Second Retry Place');
        $token = (string) Str::uuid();
        $this->service->startConversation($guest, $property, 'Original thread', $token);

        try {
            $this->service->startConversation($guest, $secondProperty, 'Original thread', $token);
            $this->fail('Reusing a retry token in another conversation should conflict.');
        } catch (MessageConflictException) {
            $this->assertDatabaseCount('conversations', 1);
            $this->assertDatabaseCount('messages', 1);
        }
    }

    public function test_existing_participants_can_send_after_property_is_unpublished(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $first = $this->service->startConversation($guest, $property, 'Initial message', (string) Str::uuid());
        $property->update(['status' => 'draft']);

        $reply = $this->service->sendMessage(
            $host,
            $first->conversation,
            'Support reply',
            (string) Str::uuid(),
        );

        $this->assertSame($host->id, $reply->sender_id);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_owner_change_makes_old_conversation_read_only_and_new_owner_inherits_nothing(): void
    {
        [$guest, $oldHost, $property] = $this->participantsAndProperty();
        $first = $this->service->startConversation($guest, $property, 'Before transfer', (string) Str::uuid());
        $conversation = $first->conversation;
        $newHost = $this->createUser('New Property Host', 'host');
        $property->update(['host_id' => $newHost->id]);

        $this->assertTrue($oldHost->can('view', $conversation));
        $this->assertTrue($guest->can('poll', $conversation));
        $this->assertTrue($guest->can('markRead', $conversation));
        $this->assertFalse($oldHost->can('send', $conversation));
        $this->assertFalse($guest->can('send', $conversation));
        $this->assertFalse($newHost->can('view', $conversation));

        try {
            $this->service->sendMessage($guest, $conversation, 'Blocked after transfer', (string) Str::uuid());
            $this->fail('Sending to a transferred conversation should be forbidden.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('messages', 1);
        }

        $newThreadMessage = $this->service->startConversation(
            $guest,
            $property->fresh(),
            'Hello new owner',
            (string) Str::uuid(),
        );

        $this->assertSame($newHost->id, $newThreadMessage->conversation->host_id);
        $this->assertNotSame($conversation->id, $newThreadMessage->conversation_id);
    }

    public function test_stored_participant_loses_access_when_role_no_longer_matches(): void
    {
        [$guest, , $property] = $this->participantsAndProperty();
        $message = $this->service->startConversation($guest, $property, 'Role protected', (string) Str::uuid());
        $conversation = $message->conversation;
        $guest->update(['role' => 'admin']);

        $this->assertFalse($guest->can('view', $conversation));
        $this->assertFalse($guest->can('poll', $conversation));
        $this->assertFalse($guest->can('send', $conversation));
        $this->assertFalse($guest->can('markRead', $conversation));
    }

    public function test_non_guest_cannot_start_and_unavailable_property_is_rejected(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();

        try {
            $this->service->startConversation($host, $property, 'Not a guest', (string) Str::uuid());
            $this->fail('A host should not initiate through guest discovery.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('conversations', 0);
        }

        $property->update(['status' => 'draft']);

        $this->expectException(ValidationException::class);
        $this->service->startConversation($guest, $property, 'Draft property', (string) Str::uuid());
    }

    public function test_mark_read_only_advances_displayed_incoming_unread_messages(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $guestMessage = $this->service->startConversation($guest, $property, 'Guest message', (string) Str::uuid());
        $conversation = $guestMessage->conversation;
        $firstHostMessage = $this->service->sendMessage($host, $conversation, 'First host reply', (string) Str::uuid());
        $laterHostMessage = $this->service->sendMessage($host, $conversation, 'Later host reply', (string) Str::uuid());

        $updated = $this->service->markRead($guest, $conversation, $firstHostMessage->id);
        $firstReadAt = $firstHostMessage->fresh()->read_at;

        $this->assertSame(1, $updated);
        $this->assertNotNull($firstReadAt);
        $this->assertNull($guestMessage->fresh()->read_at);
        $this->assertNull($laterHostMessage->fresh()->read_at);
        $this->assertSame(0, $this->service->markRead($guest, $conversation, $firstHostMessage->id));
        $this->assertTrue($firstReadAt->equalTo($firstHostMessage->fresh()->read_at));
    }

    public function test_nonparticipant_cannot_mark_read_and_message_must_belong_to_conversation(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $message = $this->service->startConversation($guest, $property, 'Protected conversation', (string) Str::uuid());
        $outsider = $this->createUser('Outside Guest', 'guest');

        try {
            $this->service->markRead($outsider, $message->conversation, $message->id);
            $this->fail('An outsider should not mark this conversation read.');
        } catch (AuthorizationException) {
            $this->assertNull($message->fresh()->read_at);
        }

        $otherProperty = $this->createProperty($host, 'Other Read Place');
        $otherMessage = $this->service->startConversation($outsider, $otherProperty, 'Other thread', (string) Str::uuid());

        $this->expectException(ValidationException::class);
        $this->service->markRead($guest, $message->conversation, $otherMessage->id);
    }

    private function participantsAndProperty(): array
    {
        $guest = $this->createUser('Service Guest', 'guest');
        $host = $this->createUser('Service Host', 'host');

        return [$guest, $host, $this->createProperty($host, 'Service Place')];
    }

    private function createUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.Str::uuid().'@example.test',
            'role' => $role,
            'password' => 'Messaging-password-123',
        ]);
    }

    private function createProperty(User $host, string $title): Property
    {
        return Property::create([
            'host_id' => $host->id,
            'title' => $title,
            'location' => 'Dhaka',
            'nightly_rate' => 5000,
            'status' => 'published',
            'guest_capacity' => 2,
        ]);
    }
}
