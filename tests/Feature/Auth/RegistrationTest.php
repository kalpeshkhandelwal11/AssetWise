<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'Admin@1234',
            'password_confirmation' => 'Admin@1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function test_registration_fails_with_weak_password(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $existing = \App\Models\User::factory()->create(['email' => 'dup@example.com']);

        $this->post('/register', [
            'name'                  => 'Another User',
            'email'                 => 'dup@example.com',
            'password'              => 'Admin@1234',
            'password_confirmation' => 'Admin@1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_fails_when_password_confirmation_mismatch(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'Admin@1234',
            'password_confirmation' => 'Different@99',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_fails_without_name(): void
    {
        $this->post('/register', [
            'email'                 => 'test@example.com',
            'password'              => 'Admin@1234',
            'password_confirmation' => 'Admin@1234',
        ])->assertSessionHasErrors('name');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_register(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect('/dashboard');
    }
}
