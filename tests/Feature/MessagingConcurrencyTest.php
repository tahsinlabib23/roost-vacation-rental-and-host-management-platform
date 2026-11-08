<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingConcurrencyTest extends TestCase
{
    public function test_parallel_initiation_and_send_retries_are_serialized_and_idempotent(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $suffix = (string) Str::uuid();
        $guest = $this->createUser('Concurrent Guest', "concurrent-guest-{$suffix}@example.test", 'guest');
        $host = $this->createUser('Concurrent Host', "concurrent-host-{$suffix}@example.test", 'host');
        $property = $this->createProperty($host, "Concurrent Place {$suffix}");

        try {
            $guestId = $guest->id;
            $hostId = $host->id;
            $propertyId = $property->id;
            $startToken = (string) Str::uuid();

            $startTask = fn () => app(\App\Services\MessagingService::class)
                ->startConversation(
                    \App\Models\User::query()->findOrFail($guestId),
                    \App\Models\Property::query()->findOrFail($propertyId),
                    'Parallel first message',
                    $startToken,
                )->id;

            $startResults = Concurrency::driver('process')->run([$startTask, $startTask], 20);

            $this->assertSame($startResults[0], $startResults[1]);
            $conversation = Conversation::query()
                ->where('property_id', $propertyId)
                ->where('guest_id', $guestId)
                ->where('host_id', $hostId)
                ->sole();
            $this->assertSame(1, $conversation->messages()->count());

            $conversationId = $conversation->id;
            $sendToken = (string) Str::uuid();
            $sendTask = fn () => app(\App\Services\MessagingService::class)
                ->sendMessage(
                    \App\Models\User::query()->findOrFail($hostId),
                    \App\Models\Conversation::query()->findOrFail($conversationId),
                    'Parallel host reply',
                    $sendToken,
                )->id;

            $sendResults = Concurrency::driver('process')->run([$sendTask, $sendTask], 20);

            $this->assertSame($sendResults[0], $sendResults[1]);
            $this->assertSame(2, $conversation->messages()->count());
        } finally {
            Conversation::query()->where('property_id', $property->id)->delete();
            Property::query()->whereKey($property->id)->delete();
            User::query()->whereKey([$guest->id, $host->id])->delete();
        }
    }

    private function createUser(string $name, string $email, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => 'Concurrency-password-123',
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
