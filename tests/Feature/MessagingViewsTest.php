<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_host_inboxes_render_role_specific_controls_and_navigation(): void
    {
        [$guest, $host, , $conversation] = $this->conversationFixture();
        $this->createMessage($conversation, $guest, 'Visible conversation', now());

        $this->actingAs($guest)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Find a host')
            ->assertSee('Enter both the host name and property name.')
            ->assertSee('href="'.route('messages.index').'"', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($host)->get(route('messages.index'))
            ->assertOk()
            ->assertDontSee('Find a host')
            ->assertSee('Endpoint Guest');
    }

    public function test_ordinary_guest_search_renders_first_message_form_without_creating_a_thread(): void
    {
        $guest = $this->createUser('View Search Guest', 'guest');
        $host = $this->createUser('View Search Host', 'host');
        $property = $this->createProperty($host, 'View Search Cottage');

        $this->actingAs($guest)->get(route('messages.search', [
            'host_name' => 'View Search Host',
            'property_name' => 'View Search Cottage',
        ]))->assertOk()
            ->assertSee('Search results')
            ->assertSee($property->title)
            ->assertSee('Send first message');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_selected_thread_escapes_message_html_and_preserves_standard_controls(): void
    {
        [$guest, , , $conversation] = $this->conversationFixture();
        $this->createMessage($conversation, $guest, '<script>alert("unsafe")</script>', now());

        $this->actingAs($guest)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertSee('Back to conversations')
            ->assertSee('Send message');
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
            'password' => 'View-password-123',
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
