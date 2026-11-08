<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_messaging_relationships_and_latest_message_work(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $conversation = $this->createConversation($property, $guest, $host);
        $firstMessage = $this->createMessage($conversation, $guest, 'First message');
        $latestMessage = $this->createMessage($conversation, $host, 'Latest message');

        $this->assertTrue($conversation->property->is($property));
        $this->assertTrue($conversation->guest->is($guest));
        $this->assertTrue($conversation->host->is($host));
        $this->assertCount(2, $conversation->messages);
        $this->assertTrue($conversation->latestMessage->is($latestMessage));
        $this->assertTrue($latestMessage->conversation->is($conversation));
        $this->assertTrue($latestMessage->sender->is($host));
        $this->assertTrue($guest->guestConversations->contains($conversation));
        $this->assertTrue($host->hostConversations->contains($conversation));
        $this->assertTrue($guest->sentMessages->contains($firstMessage));
        $this->assertTrue($property->conversations->contains($conversation));
    }

    public function test_participant_scope_is_grouped_with_later_filters(): void
    {
        [$guest, $host, $firstProperty] = $this->participantsAndProperty();
        $secondHost = $this->createUser('Second Persistence Host', 'host');
        $secondProperty = $this->createProperty($secondHost, 'Second Place');
        $expected = $this->createConversation($firstProperty, $guest, $host);
        $this->createConversation($secondProperty, $guest, $secondHost);

        $results = Conversation::query()
            ->forParticipant($guest->id)
            ->where('property_id', $firstProperty->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->sole()->is($expected));
    }

    public function test_duplicate_guest_host_conversation_is_rejected_across_properties(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $otherProperty = $this->createProperty($host, 'Other Property For Same Pair');
        $this->createConversation($property, $guest, $host);

        $this->expectException(QueryException::class);
        $this->createConversation($otherProperty, $guest, $host);
    }

    public function test_deleting_a_conversation_cascades_to_its_messages(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $conversation = $this->createConversation($property, $guest, $host);
        $message = $this->createMessage($conversation, $guest, 'Keep this until the thread is deleted');

        $conversation->delete();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_referenced_property_cannot_be_hard_deleted(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $this->createConversation($property, $guest, $host);

        $this->expectException(QueryException::class);
        $property->delete();
    }

    public function test_referenced_participant_cannot_be_hard_deleted(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $this->createConversation($property, $guest, $host);

        $this->expectException(QueryException::class);
        $guest->delete();
    }

    public function test_referenced_message_sender_cannot_be_hard_deleted(): void
    {
        [$guest, $host, $property] = $this->participantsAndProperty();
        $conversation = $this->createConversation($property, $guest, $host);
        $this->createMessage($conversation, $guest, 'Protected sender');

        $conversation->guest()->dissociate();
        $conversation->guest_id = $this->createUser('Replacement Guest', 'guest')->id;
        $conversation->save();

        $this->expectException(QueryException::class);
        $guest->delete();
    }

    private function participantsAndProperty(): array
    {
        $guest = $this->createUser('Persistence Guest', 'guest');
        $host = $this->createUser('Persistence Host', 'host');

        return [$guest, $host, $this->createProperty($host, 'Persistence Place')];
    }

    private function createUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.Str::uuid().'@example.test',
            'role' => $role,
            'password' => 'Persistence-password-123',
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
