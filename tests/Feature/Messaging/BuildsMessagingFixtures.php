<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsMessagingFixtures
{
    protected function user(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.Str::uuid().'@example.test',
            'role' => $role,
            'password' => 'Messaging-acceptance-123',
        ]);
    }

    protected function property(User $host, string $title, string $status = 'published'): Property
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

    protected function conversation(Property $property, User $guest, User $host): Conversation
    {
        $conversation = new Conversation;
        $conversation->property()->associate($property);
        $conversation->guest()->associate($guest);
        $conversation->host()->associate($host);
        $conversation->save();

        return $conversation;
    }

    protected function message(
        Conversation $conversation,
        User $sender,
        string $body,
        $createdAt = null,
    ): Message {
        $createdAt ??= now();
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

    protected function fixture(string $prefix = 'Acceptance'): array
    {
        $guest = $this->user("{$prefix} Guest", 'guest');
        $host = $this->user("{$prefix} Host", 'host');
        $property = $this->property($host, "{$prefix} Place");

        return [$guest, $host, $property, $this->conversation($property, $guest, $host)];
    }
}
