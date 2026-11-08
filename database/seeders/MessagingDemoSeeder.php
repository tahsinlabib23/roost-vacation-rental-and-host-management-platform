<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class MessagingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Messaging demo data may only be seeded in local or testing environments.');
        }

        $guestOne = $this->demoUser('f6.guest1@example.test', 'F6 Guest One', 'guest');
        $guestTwo = $this->demoUser('f6.guest2@example.test', 'F6 Guest Two', 'guest');
        $hostOne = $this->demoUser('f6.host1@example.test', 'Maya Rahman', 'host');
        $hostTwo = $this->demoUser('f6.host2@example.test', 'Nadia Karim', 'host');

        $gardenCottage = $this->demoProperty($hostOne, 'Maya Garden Cottage', 'Dhanmondi, Dhaka', 'published');
        $this->demoProperty($hostOne, 'Maya Garden Loft', 'Banani, Dhaka', 'published');
        $riverHouse = $this->demoProperty($hostTwo, 'Nadia River House', 'Uttara, Dhaka', 'published');
        $this->demoProperty($hostTwo, 'Nadia Hidden Cabin', 'Savar, Dhaka', 'draft');

        $messaging = app(MessagingService::class);
        $firstMessage = $messaging->startConversation(
            $guestOne,
            $gardenCottage,
            'Hello Maya, is the garden cottage available for a quiet weekend?',
            $this->stableUuid('message-1'),
        );

        for ($number = 2; $number <= 56; $number++) {
            $sender = $number % 2 === 0 ? $hostOne : $guestOne;
            $body = $number % 2 === 0
                ? "Demo host reply {$number}: Yes, I can help with the cottage and arrival details."
                : "Demo guest message {$number}: Thank you. I have one more question about the stay.";

            $messaging->sendMessage(
                $sender,
                $firstMessage->conversation,
                $body,
                $this->stableUuid("message-{$number}"),
            );
        }

        // Preserve a real first-contact example for the second guest and host.
        Conversation::query()
            ->where('guest_id', $guestTwo->id)
            ->where('host_id', $hostTwo->id)
            ->where('property_id', $riverHouse->id)
            ->delete();
    }

    private function demoUser(string $email, string $name, string $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'password' => Hash::make('RoostDemo123!'),
            ],
        );
    }

    private function demoProperty(
        User $host,
        string $title,
        string $location,
        string $status,
    ): Property {
        return Property::query()->updateOrCreate(
            ['host_id' => $host->id, 'title' => $title],
            [
                'location' => $location,
                'nightly_rate' => 6500,
                'status' => $status,
                'guest_capacity' => 4,
            ],
        );
    }

    private function stableUuid(string $key): string
    {
        $hex = md5("roost-f6-demo-{$key}");
        $hex[12] = '4';
        $hex[16] = '8';

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
