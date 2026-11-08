<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\MessagingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MessagingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_preserves_first_contact_pair(): void
    {
        $this->seed(MessagingDemoSeeder::class);

        $countsAfterFirstRun = [
            User::query()->where('email', 'like', 'f6.%@example.test')->count(),
            Property::query()->whereIn('title', [
                'Maya Garden Cottage',
                'Maya Garden Loft',
                'Nadia River House',
                'Nadia Hidden Cabin',
            ])->count(),
            Conversation::query()->count(),
            Message::query()->count(),
        ];

        $this->seed(MessagingDemoSeeder::class);

        $this->assertSame($countsAfterFirstRun, [
            User::query()->where('email', 'like', 'f6.%@example.test')->count(),
            Property::query()->whereIn('title', [
                'Maya Garden Cottage',
                'Maya Garden Loft',
                'Nadia River House',
                'Nadia Hidden Cabin',
            ])->count(),
            Conversation::query()->count(),
            Message::query()->count(),
        ]);
        $this->assertSame([4, 4, 1, 56], $countsAfterFirstRun);

        $guestTwo = User::query()->where('email', 'f6.guest2@example.test')->sole();
        $hostTwo = User::query()->where('email', 'f6.host2@example.test')->sole();
        $this->assertFalse(Conversation::query()
            ->where('guest_id', $guestTwo->id)
            ->where('host_id', $hostTwo->id)
            ->exists());
        $this->assertSame(1, Property::query()->where('status', 'draft')->count());
    }

    public function test_demo_seeder_refuses_nonlocal_environments(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new MessagingDemoSeeder)->run();
            $this->fail('The demo seeder should refuse production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local or testing', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
