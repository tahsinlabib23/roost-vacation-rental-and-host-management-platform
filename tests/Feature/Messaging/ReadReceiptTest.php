<?php

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadReceiptTest extends TestCase
{
    use BuildsMessagingFixtures, RefreshDatabase;

    public function test_only_received_displayed_messages_are_marked_and_later_messages_remain_unread(): void
    {
        [$guest, $host, , $conversation] = $this->fixture('Read');
        $own = $this->message($conversation, $guest, 'Own message');
        $displayed = $this->message($conversation, $host, 'Displayed incoming');
        $later = $this->message($conversation, $host, 'Below viewport');

        $this->actingAs($guest)->postJson(route('messages.read', $conversation), [
            'through_message_id' => $displayed->id,
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertNull($own->fresh()->read_at);
        $this->assertNotNull($displayed->fresh()->read_at);
        $this->assertNull($later->fresh()->read_at);
    }

    public function test_wrong_conversation_cursor_is_rejected(): void
    {
        [$guest, , , $conversation] = $this->fixture('Cursor One');
        $otherHost = $this->user('Cursor Other Host', 'host');
        $otherProperty = $this->property($otherHost, 'Cursor Other Place');
        $other = $this->conversation($otherProperty, $guest, $otherHost);
        $otherMessage = $this->message($other, $otherHost, 'Wrong cursor');

        $this->actingAs($guest)->postJson(route('messages.read', $conversation), [
            'through_message_id' => $otherMessage->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('through_message_id');
    }

    public function test_partner_read_watermark_updates_without_new_messages(): void
    {
        [$guest, $host, , $conversation] = $this->fixture('Watermark');
        $guestMessage = $this->message($conversation, $guest, 'Waiting for receipt');

        $this->actingAs($host)->postJson(route('messages.read', $conversation), [
            'through_message_id' => $guestMessage->id,
        ])->assertOk();

        $this->actingAs($guest)->getJson(route('messages.items', [
            'conversation' => $conversation,
            'after_id' => $guestMessage->id,
        ]))->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('partner_read_through_id', $guestMessage->id);
    }
}
