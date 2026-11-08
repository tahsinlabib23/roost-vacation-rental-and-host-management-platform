<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_both_literal_name_matches_and_returns_only_public_fields(): void
    {
        $guest = $this->createUser('Search Guest', 'guest');
        $alice = $this->createUser('Alice 100%_ Host', 'host');
        $bob = $this->createUser('Bob Host', 'host');
        $invalidOwner = $this->createUser('Alice 100%_ Host', 'guest');

        $expected = $this->createProperty($alice, 'Lake 100%_ House');
        $this->createProperty($alice, 'City 100AA Loft');
        $this->createProperty($bob, 'Lake 100%_ House');
        $this->createProperty($alice, 'Lake 100%_ Draft', 'draft');
        $this->createProperty($invalidOwner, 'Lake 100%_ House');

        $response = $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => 'Alice 100%_',
            'property_name' => 'Lake 100%_',
        ]));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.property.id', $expected->id)
            ->assertJsonPath('data.0.property.title', 'Lake 100%_ House')
            ->assertJsonPath('data.0.host.name', 'Alice 100%_ Host')
            ->assertJsonMissingPath('data.0.host.email')
            ->assertJsonMissingPath('data.0.property.host_id');
        $response->assertDontSee($alice->email);

    }

    public function test_search_is_guest_only_and_is_limited_to_twenty_results_per_page(): void
    {
        $guest = $this->createUser('Pagination Guest', 'guest');
        $host = $this->createUser('Pagination Host', 'host');

        foreach (range(1, 21) as $number) {
            $this->createProperty($host, sprintf('Pagination Place %02d', $number));
        }

        $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => 'Pagination Host',
            'property_name' => 'Pagination Place',
        ]))->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($host)->getJson(route('messages.search', [
            'host_name' => 'Pagination Host',
            'property_name' => 'Pagination Place',
        ]))->assertForbidden();
    }

    public function test_inbox_is_participant_scoped_ordered_and_private(): void
    {
        $guest = $this->createUser('Inbox Guest', 'guest');
        $otherGuest = $this->createUser('Other Inbox Guest', 'guest');
        $host = $this->createUser('Inbox Host', 'host');
        $firstProperty = $this->createProperty($host, 'First Inbox Place');
        $secondProperty = $this->createProperty($host, 'Second Inbox Place');
        $first = $this->createConversation($firstProperty, $guest, $host);
        $second = $this->createConversation($secondProperty, $guest, $host);
        $outsider = $this->createConversation($firstProperty, $otherGuest, $host);
        $this->createMessage($first, $host, 'Older unread', now()->subMinute());
        $this->createMessage($second, $host, 'Newest unread', now());
        $this->createMessage($outsider, $host, 'Private outsider message', now()->addMinute());

        $response = $this->actingAs($guest)->getJson(route('messages.inbox'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonMissingPath('data.0.counterpart.email');
        $response->assertDontSee('Private outsider message')->assertDontSee($host->email);
    }

    public function test_history_uses_fifty_message_cursors_and_includes_read_watermark(): void
    {
        [$guest, $host, $property, $conversation] = $this->conversationFixture();
        $messages = collect();

        foreach (range(1, 55) as $number) {
            $sender = $number % 2 === 0 ? $host : $guest;
            $messages->push($this->createMessage($conversation, $sender, "History {$number}", now()->addSeconds($number)));
        }

        $guestSent = $messages->first(fn (Message $message) => $message->sender_id === $guest->id);
        $guestSent->update(['read_at' => now()]);

        $initial = $this->actingAs($guest)->getJson(route('messages.items', $conversation));
        $initial->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0.body', 'History 6')
            ->assertJsonPath('data.49.body', 'History 55')
            ->assertJsonPath('partner_read_through_id', $guestSent->id)
            ->assertJsonMissingPath('data.0.client_message_id');

        $this->actingAs($guest)->getJson(route('messages.items', [
            'conversation' => $conversation,
            'before_id' => $messages[5]->id,
        ]))->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.body', 'History 1')
            ->assertJsonPath('has_more', false);

        $this->actingAs($guest)->getJson(route('messages.items', [
            'conversation' => $conversation,
            'after_id' => $messages[52]->id,
        ]))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'History 54')
            ->assertJsonPath('data.1.body', 'History 55');
    }

    public function test_outsider_cannot_read_send_or_mark_another_conversation(): void
    {
        [, , , $conversation] = $this->conversationFixture();
        $outsider = $this->createUser('Endpoint Outsider', 'guest');
        $message = $this->createMessage($conversation, $conversation->host, 'Protected endpoint message', now());

        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('messages.items', $conversation))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('messages.store', $conversation), [
            'body' => 'No access',
            'client_message_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->actingAs($outsider)->postJson(route('messages.read', $conversation), [
            'through_message_id' => $message->id,
        ])->assertForbidden();
    }

    public function test_standard_forms_start_send_and_mark_displayed_messages_read(): void
    {
        $guest = $this->createUser('Form Guest', 'guest');
        $host = $this->createUser('Form Host', 'host');
        $property = $this->createProperty($host, 'Form Place');

        $this->actingAs($guest)->post(route('messages.conversations.store'), [
            'property_id' => $property->id,
            'body' => 'First form message',
            'client_message_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertSame($guest->id, $conversation->guest_id);
        $this->assertSame($host->id, $conversation->host_id);

        $this->actingAs($host)->post(route('messages.store', $conversation), [
            'body' => 'Host form reply',
            'client_message_id' => (string) Str::uuid(),
        ])->assertRedirect(route('messages.show', $conversation));

        $reply = Message::query()->where('sender_id', $host->id)->sole();
        $this->actingAs($guest)->post(route('messages.read', $conversation), [
            'through_message_id' => $reply->id,
        ])->assertRedirect(route('messages.show', $conversation));
        $this->assertNotNull($reply->fresh()->read_at);
    }

    public function test_json_write_endpoints_return_explicit_private_payloads(): void
    {
        $guest = $this->createUser('JSON Guest', 'guest');
        $host = $this->createUser('JSON Host', 'host');
        $property = $this->createProperty($host, 'JSON Place');

        $start = $this->actingAs($guest)->postJson(route('messages.conversations.store'), [
            'property_id' => $property->id,
            'body' => 'JSON first message',
            'client_message_id' => (string) Str::uuid(),
        ]);

        $start->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('conversation.property.title', 'JSON Place')
            ->assertJsonPath('conversation.counterpart.name', 'JSON Host')
            ->assertJsonPath('message.body', 'JSON first message')
            ->assertJsonMissingPath('conversation.counterpart.email')
            ->assertJsonMissingPath('message.client_message_id');

        $conversation = Conversation::query()->sole();
        $send = $this->actingAs($host)->postJson(route('messages.store', $conversation), [
            'body' => 'JSON host reply',
            'client_message_id' => (string) Str::uuid(),
        ]);
        $send->assertCreated()
            ->assertJsonPath('message.body', 'JSON host reply')
            ->assertJsonMissingPath('message.client_message_id');

        $replyId = $send->json('message.id');
        $this->actingAs($guest)->postJson(route('messages.read', $conversation), [
            'through_message_id' => $replyId,
        ])->assertOk()->assertJsonPath('updated', 1);
    }

    private function conversationFixture(): array
    {
        $guest = $this->createUser('Endpoint Guest', 'guest');
        $host = $this->createUser('Endpoint Host', 'host');
        $property = $this->createProperty($host, 'Endpoint Place');
        $conversation = $this->createConversation($property, $guest, $host);

        return [$guest, $host, $property, $conversation];
    }

    private function createUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.Str::uuid().'@example.test',
            'role' => $role,
            'password' => 'Endpoint-password-123',
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

    private function createMessage(Conversation $conversation, User $sender, string $body, $createdAt): Message
    {
        $message = new Message([
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);
        $message->created_at = $createdAt;
        $message->updated_at = $createdAt;
        $message->save();
        $conversation->forceFill(['last_message_at' => $createdAt])->save();

        return $message;
    }
}
