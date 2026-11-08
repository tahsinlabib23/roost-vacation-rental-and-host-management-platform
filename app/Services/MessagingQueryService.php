<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessagingQueryService
{
    private const INBOX_PAGE_SIZE = 20;

    private const SEARCH_PAGE_SIZE = 20;

    private const MESSAGE_BATCH_SIZE = 50;

    public function inbox(User $user): LengthAwarePaginator
    {
        return Conversation::query()
            ->forParticipant($user->id)
            ->select(['id', 'property_id', 'guest_id', 'host_id', 'last_message_at', 'created_at', 'updated_at'])
            ->with([
                'property:id,title',
                'guest:id,name',
                'host:id,name',
                'latestMessage:id,conversation_id,sender_id,body,read_at,created_at',
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(self::INBOX_PAGE_SIZE)
            ->withQueryString()
            ->through(fn (Conversation $conversation) => $this->conversationSummary($conversation, $user));
    }

    public function searchHosts(string $hostName, string $propertyName): LengthAwarePaginator
    {
        $hostPattern = '%'.$this->escapeLikeLiteral($hostName).'%';
        $propertyPattern = '%'.$this->escapeLikeLiteral($propertyName).'%';

        return Property::query()
            ->select(['id', 'host_id', 'title', 'location'])
            ->where('status', 'published')
            ->whereRaw("properties.title LIKE ? ESCAPE '!'", [$propertyPattern])
            ->whereHas('host', fn ($query) => $query
                ->where('role', 'host')
                ->whereRaw("users.name LIKE ? ESCAPE '!'", [$hostPattern]))
            ->with('host:id,name')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::SEARCH_PAGE_SIZE)
            ->withQueryString()
            ->through(fn (Property $property) => [
                'property' => [
                    'id' => $property->id,
                    'title' => $property->title,
                    'location' => $property->location,
                ],
                'host' => [
                    'id' => $property->host->id,
                    'name' => $property->host->name,
                ],
            ]);
    }

    public function conversation(Conversation $conversation, User $user): array
    {
        $conversation->loadMissing([
            'property:id,title',
            'guest:id,name',
            'host:id,name',
            'latestMessage:id,conversation_id,sender_id,body,read_at,created_at',
        ]);

        if (! isset($conversation->unread_count)) {
            $conversation->loadCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ]);
        }

        return $this->conversationSummary($conversation, $user);
    }

    public function history(
        Conversation $conversation,
        User $user,
        ?int $beforeId = null,
        ?int $afterId = null,
    ): array {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->select(['id', 'conversation_id', 'sender_id', 'body', 'read_at', 'created_at']);

        if ($afterId !== null) {
            $query->where('id', '>', $afterId)->orderBy('id');
        } else {
            $query->when($beforeId !== null, fn ($builder) => $builder->where('id', '<', $beforeId))
                ->orderByDesc('id');
        }

        $messages = $query->limit(self::MESSAGE_BATCH_SIZE + 1)->get();
        $hasMore = $messages->count() > self::MESSAGE_BATCH_SIZE;
        $messages = $messages->take(self::MESSAGE_BATCH_SIZE);

        if ($afterId === null) {
            $messages = $messages->reverse()->values();
        }

        $items = $messages->map(fn (Message $message) => [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'read_at' => $message->read_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
            'is_mine' => $message->sender_id === $user->id,
        ])->all();

        $partnerReadThroughId = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $user->id)
            ->whereNotNull('read_at')
            ->max('id');

        return [
            'data' => $items,
            'has_more' => $hasMore,
            'before_id' => $items[0]['id'] ?? null,
            'after_id' => $items !== [] ? $items[array_key_last($items)]['id'] : null,
            'partner_read_through_id' => $partnerReadThroughId,
        ];
    }

    public function paginatorPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'previous' => $paginator->previousPageUrl(),
            ],
        ];
    }

    private function conversationSummary(Conversation $conversation, User $user): array
    {
        $counterpart = $user->role === 'host' ? $conversation->guest : $conversation->host;
        $latest = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'property' => [
                'id' => $conversation->property->id,
                'title' => $conversation->property->title,
            ],
            'counterpart' => [
                'id' => $counterpart->id,
                'name' => $counterpart->name,
            ],
            'latest_message' => $latest ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'sender_id' => $latest->sender_id,
                'created_at' => $latest->created_at->toISOString(),
            ] : null,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
        ];
    }

    private function escapeLikeLiteral(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
