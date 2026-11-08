<?php

namespace Tests\Feature\Messaging;

use App\Exceptions\MessageConflictException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageDeliveryTest extends TestCase
{
    use BuildsMessagingFixtures, RefreshDatabase;

    public function test_same_guest_and_host_reuse_one_thread_across_different_properties(): void
    {
        $service = app(MessagingService::class);
        $guest = $this->user('Delivery Guest', 'guest');
        $host = $this->user('Delivery Host', 'host');
        $firstProperty = $this->property($host, 'Delivery One');
        $secondProperty = $this->property($host, 'Delivery Two');

        $first = $service->startConversation($guest, $firstProperty, 'First', (string) Str::uuid());
        $repeat = $service->startConversation($guest, $firstProperty, 'Repeat', (string) Str::uuid());
        $otherPropertyMessage = $service->startConversation($guest, $secondProperty, 'Other property', (string) Str::uuid());

        $this->assertSame($first->conversation_id, $repeat->conversation_id);
        $this->assertSame($first->conversation_id, $otherPropertyMessage->conversation_id);
        $this->assertSame($firstProperty->id, $first->conversation->fresh()->property_id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_message_length_boundaries_include_two_thousand_unicode_characters(): void
    {
        $guest = $this->user('Boundary Guest', 'guest');
        $host = $this->user('Boundary Host', 'host');
        $property = $this->property($host, 'Boundary Place');

        foreach (['   ', str_repeat('x', 2001)] as $invalidBody) {
            $this->actingAs($guest)->postJson(route('messages.conversations.store'), [
                'property_id' => $property->id,
                'body' => $invalidBody,
                'client_message_id' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('body');
        }

        $unicodeBody = str_repeat('界', 2000);
        $this->actingAs($guest)->postJson(route('messages.conversations.store'), [
            'property_id' => $property->id,
            'body' => $unicodeBody,
            'client_message_id' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('message.body', $unicodeBody);
    }

    public function test_retry_is_idempotent_and_conflicting_uuid_is_rejected(): void
    {
        $service = app(MessagingService::class);
        $guest = $this->user('Retry Acceptance Guest', 'guest');
        $host = $this->user('Retry Acceptance Host', 'host');
        $property = $this->property($host, 'Retry Acceptance Place');
        $token = (string) Str::uuid();

        $first = $service->startConversation($guest, $property, 'Stable body', $token);
        $retry = $service->startConversation($guest, $property, 'Stable body', $token);

        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('messages', 1);

        $this->expectException(MessageConflictException::class);
        $service->startConversation($guest, $property, 'Changed body', $token);
    }

    public function test_history_paginates_in_stable_order_without_gaps(): void
    {
        [$guest, $host, , $conversation] = $this->fixture('Ordering');
        $expectedIds = [];

        foreach (range(1, 105) as $number) {
            $sender = $number % 2 === 0 ? $host : $guest;
            $expectedIds[] = $this->message($conversation, $sender, "Ordered {$number}", now()->addSeconds($number))->id;
        }

        $latest = $this->actingAs($guest)->getJson(route('messages.items', $conversation))->json();
        $older = $this->actingAs($guest)->getJson(route('messages.items', [
            'conversation' => $conversation,
            'before_id' => $latest['before_id'],
        ]))->json();
        $oldest = $this->actingAs($guest)->getJson(route('messages.items', [
            'conversation' => $conversation,
            'before_id' => $older['before_id'],
        ]))->json();

        $actualIds = collect([$oldest['data'], $older['data'], $latest['data']])
            ->flatten(1)
            ->pluck('id')
            ->all();

        $this->assertSame($expectedIds, $actualIds);
        $this->assertCount(105, array_unique($actualIds));
        $this->assertDatabaseCount('messages', 105);
        $this->assertSame(Conversation::query()->sole()->id, $conversation->id);
        $this->assertSame(105, Message::query()->count());
    }
}
