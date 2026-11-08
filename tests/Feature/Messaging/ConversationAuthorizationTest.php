<?php

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use BuildsMessagingFixtures, RefreshDatabase;

    public function test_unrelated_guests_and_hosts_cannot_read_poll_send_or_mark_read(): void
    {
        [$guest, $host, , $conversation] = $this->fixture('Private');
        $message = $this->message($conversation, $host, 'Private message');
        $otherGuest = $this->user('Other Private Guest', 'guest');
        $otherHost = $this->user('Other Private Host', 'host');

        foreach ([$otherGuest, $otherHost] as $outsider) {
            $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
            $this->actingAs($outsider)->getJson(route('messages.items', $conversation))->assertForbidden();
            $this->actingAs($outsider)->postJson(route('messages.store', $conversation), [
                'body' => 'Forbidden',
                'client_message_id' => (string) Str::uuid(),
            ])->assertForbidden();
            $this->actingAs($outsider)->postJson(route('messages.read', $conversation), [
                'through_message_id' => $message->id,
            ])->assertForbidden();
        }

        $this->actingAs($guest)->getJson(route('messages.items', $conversation))->assertOk();
    }

    public function test_guest_cannot_inject_sender_host_or_recipient_ids(): void
    {
        [$guest, $host, $property] = array_slice($this->fixture('Injection'), 0, 3);

        $this->actingAs($guest)->postJson(route('messages.conversations.store'), [
            'property_id' => $property->id,
            'body' => 'Injected IDs',
            'client_message_id' => (string) Str::uuid(),
            'sender_id' => $host->id,
            'host_id' => $guest->id,
            'recipient_id' => $guest->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_id', 'host_id', 'recipient_id']);
    }

    public function test_ownership_transfer_keeps_history_private_and_original_pair_connected(): void
    {
        [$guest, $oldHost, $property, $conversation] = $this->fixture('Transfer');
        $this->message($conversation, $guest, 'History before transfer');
        $newHost = $this->user('Transfer New Host', 'host');
        $property->update(['host_id' => $newHost->id]);

        $this->actingAs($newHost)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($guest)->get(route('messages.show', $conversation))->assertOk();
        $this->actingAs($oldHost)->postJson(route('messages.store', $conversation), [
            'body' => 'Continue the existing conversation',
            'client_message_id' => (string) Str::uuid(),
        ])->assertCreated();
    }
}
