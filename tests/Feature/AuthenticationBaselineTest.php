<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    #[DataProvider('roles')]
    public function test_registration_and_dashboard_work(string $role): void
    {
        $this->post('/register', [
            'name' => 'Baseline User', 'email' => "{$role}@example.test", 'role' => $role,
            'password' => 'Baseline-password-123', 'password_confirmation' => 'Baseline-password-123',
        ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

        $user = User::where('email', "{$role}@example.test")->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('Baseline-password-123', $user->password));
        $this->get('/dashboard')->assertOk();
    }

    public function test_existing_user_can_login_and_logout(): void
    {
        $user = User::create([
            'name' => 'Returning Guest', 'email' => 'returning@example.test',
            'role' => 'guest', 'password' => Hash::make('Baseline-password-123'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Baseline-password-123'])
            ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public static function roles(): array
    {
        return [['host'], ['guest']];
    }
}
