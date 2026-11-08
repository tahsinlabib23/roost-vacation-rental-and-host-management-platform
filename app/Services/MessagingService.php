<?php

namespace App\Services;

use App\Exceptions\MessageConflictException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    public function startConversation(
        User $guest,
        Property $property,
        string $body,
        string $clientMessageId,
    ): Message {
        if ($guest->role !== 'guest') {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($guest, $property, $body, $clientMessageId) {
            // Serialize conversation starts for this guest so simultaneous
            // searches cannot create two conversations with the same host.
            $lockedGuest = User::query()->lockForUpdate()->findOrFail($guest->id);

            if ($lockedGuest->role !== 'guest') {
                throw new AuthorizationException;
            }

            $lockedProperty = Property::query()
                ->with('host')
                ->lockForUpdate()
                ->findOrFail($property->id);

            if ($lockedProperty->status !== 'published' || $lockedProperty->host?->role !== 'host') {
                throw ValidationException::withMessages([
                    'property_id' => 'The selected property is not available for messaging.',
                ]);
            }

            $conversation = Conversation::query()
                ->where('guest_id', $lockedGuest->id)
                ->where('host_id', $lockedProperty->host_id)
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $conversation = new Conversation;
                $conversation->property()->associate($lockedProperty);
                $conversation->guest()->associate($lockedGuest);
                $conversation->host()->associate($lockedProperty->host);
                $conversation->save();
            }

            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);

            return $this->storeMessage($conversation, $guest, $body, $clientMessageId);
        }, 3);
    }

    public function sendMessage(
        User $sender,
        Conversation $conversation,
        string $body,
        string $clientMessageId,
    ): Message {
        return DB::transaction(function () use ($sender, $conversation, $body, $clientMessageId) {
            $lockedConversation = Conversation::query()
                ->with('property')
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            Gate::forUser($sender)->authorize('send', $lockedConversation);

            return $this->storeMessage($lockedConversation, $sender, $body, $clientMessageId);
        }, 3);
    }

    public function markRead(User $reader, Conversation $conversation, int $throughMessageId): int
    {
        return DB::transaction(function () use ($reader, $conversation, $throughMessageId) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            Gate::forUser($reader)->authorize('markRead', $lockedConversation);

            $belongsToConversation = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->whereKey($throughMessageId)
                ->exists();

            if (! $belongsToConversation) {
                throw ValidationException::withMessages([
                    'through_message_id' => 'The selected message does not belong to this conversation.',
                ]);
            }

            return Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->where('sender_id', '!=', $reader->id)
                ->whereNull('read_at')
                ->where('id', '<=', $throughMessageId)
                ->update(['read_at' => now()]);
        }, 3);
    }

    private function storeMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        string $clientMessageId,
    ): Message {
        $existing = $this->findRetry($sender, $clientMessageId);

        if ($existing) {
            return $this->resolveRetry($existing, $conversation, $body);
        }

        $message = new Message([
            'body' => $body,
            'client_message_id' => $clientMessageId,
        ]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);

        try {
            $message->save();
        } catch (QueryException $exception) {
            $existing = $this->findRetry($sender, $clientMessageId);

            if (! $existing) {
                throw $exception;
            }

            return $this->resolveRetry($existing, $conversation, $body);
        }

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        return $message;
    }

    private function findRetry(User $sender, string $clientMessageId): ?Message
    {
        return Message::query()
            ->where('sender_id', $sender->id)
            ->where('client_message_id', $clientMessageId)
            ->first();
    }

    private function resolveRetry(Message $existing, Conversation $conversation, string $body): Message
    {
        if ($existing->conversation_id !== $conversation->id || $existing->body !== $body) {
            throw new MessageConflictException;
        }

        return $existing;
    }
}
