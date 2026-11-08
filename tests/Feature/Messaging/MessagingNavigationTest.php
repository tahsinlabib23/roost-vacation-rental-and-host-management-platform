<?php

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingNavigationTest extends TestCase
{
    use BuildsMessagingFixtures, RefreshDatabase;

    public function test_script_text_is_literal_json_is_private_and_host_has_no_guest_search(): void
    {
        [$guest, $host, , $conversation] = $this->fixture('Rendering');
        $scriptBody = '<script>window.attack=true</script>';
        $this->message($conversation, $guest, $scriptBody);

        $this->actingAs($host)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('&lt;script&gt;window.attack=true&lt;/script&gt;', false)
            ->assertDontSee('<script>window.attack=true</script>', false)
            ->assertDontSee('Find a host')
            ->assertSee(asset('css/messages.css'))
            ->assertSee(asset('js/messages.js'));

        $json = $this->actingAs($guest)->getJson(route('messages.inbox'));
        $json->assertOk()
            ->assertJsonMissingPath('data.0.counterpart.email')
            ->assertJsonMissingPath('data.0.counterpart.password');
        $json->assertDontSee($host->email)->assertDontSee($host->password);
    }

    public function test_messages_navigation_returns_to_every_dashboard_section(): void
    {
        [$guest, $host] = $this->fixture('Navigation');

        $this->actingAs($guest)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('dashboard').'#reservation-list"', false)
            ->assertSee('href="'.route('messages.index').'"', false);

        $this->actingAs($host)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'#properties"', false)
            ->assertSee('href="'.route('dashboard').'#reservation-list"', false);
    }

    public function test_registration_authentication_and_both_dashboards_still_work(): void
    {
        $guest = $this->user('Regression Guest', 'guest');
        $host = $this->user('Regression Host', 'host');

        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->actingAs($guest)->get(route('dashboard'))->assertOk();
        $this->actingAs($host)->get(route('dashboard'))->assertOk();
        $this->actingAs($guest)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_empty_thread_and_many_conversation_inbox_render_safely(): void
    {
        $host = $this->user('Many Conversations Host', 'host');
        $selected = null;

        foreach (range(1, 21) as $number) {
            $guest = $this->user("Many Guest {$number}", 'guest');
            $property = $this->property($host, "Many Place {$number}");
            $conversation = $this->conversation($property, $guest, $host);
            $selected ??= $conversation;
        }

        $this->actingAs($host)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Page 1 of 2')
            ->assertSee('Many Guest 21');

        $this->actingAs($host)->get(route('messages.show', $selected))
            ->assertOk()
            ->assertSee('No messages in this conversation')
            ->assertSee('Send message');
    }
}
