<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLayoutComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_dashboard_receives_scoped_layout_data_and_navigation(): void
    {
        $host = $this->createUser('Layout Host', 'host');
        $otherHost = $this->createUser('Other Host', 'host');

        $this->createProperty($host, 'Host Place One');
        $this->createProperty($host, 'Host Place Two');
        $this->createProperty($otherHost, 'Other Host Place');

        $response = $this->actingAs($host)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Layout Host')
            ->assertSee('<span class="nav-count">2</span>', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('dashboard').'#properties"', false)
            ->assertSee('href="'.route('dashboard').'#reservation-list"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_guest_dashboard_receives_guest_layout_data_and_navigation(): void
    {
        $guest = $this->createUser('Layout Guest', 'guest');

        $response = $this->actingAs($guest)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Layout Guest')
            ->assertSee('Travel')
            ->assertSee('My stays')
            ->assertSee('href="'.route('dashboard').'#reservation-list"', false)
            ->assertDontSee('My places')
            ->assertDontSee('class="nav-count"', false);
    }

    private function createUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.$role.'@example.test',
            'role' => $role,
            'password' => 'Layout-password-123',
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
