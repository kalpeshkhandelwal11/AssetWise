<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    // ── Rendering ──────────────────────────────────────────────────────────

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    // ── Successful login ───────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_is_redirected_to_dashboard_after_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234'])
            ->assertRedirect(route('dashboard'));
    }

    // ── Failed login ───────────────────────────────────────────────────────

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->post('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'Admin@1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email(): void
    {
        $this->post('/login', ['password' => 'Admin@1234'])
            ->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $this->post('/login', ['email' => 'user@example.com'])
            ->assertSessionHasErrors('password');
    }

    // ── Deactivated account ────────────────────────────────────────────────

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ── Logout ─────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guest_cannot_access_logout(): void
    {
        // The auth middleware redirects unauthenticated users to /login
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    // ── Protected routes ───────────────────────────────────────────────────

    public function test_profile_requires_auth(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200);
    }
}
