<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    // ── Active session passes through ──────────────────────────────────────

    public function test_active_session_within_lifetime_is_allowed(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->get('/dashboard')->assertStatus(200); // seeds _last_activity_at

        $this->travel(10)->minutes();

        $this->get('/dashboard')->assertStatus(200);

        $this->travelBack();
    }

    // ── Expired session is rejected ────────────────────────────────────────

    public function test_expired_idle_session_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->get('/dashboard')->assertStatus(200); // seeds _last_activity_at

        // Advance past the 480-minute (8-hour) global default
        $this->travel(9)->hours();

        $this->get('/dashboard')->assertRedirect('/login');

        $this->travelBack();
    }

    public function test_expired_session_shows_expiry_message(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->get('/dashboard')->assertStatus(200);
        $this->travel(9)->hours();

        $this->followingRedirects()->get('/dashboard')->assertSee('session has expired');

        $this->travelBack();
    }

    // ── Role-configurable lifetime ─────────────────────────────────────────

    public function test_user_with_custom_role_lifetime_is_respected(): void
    {
        $user = $this->createUserWithRole('Viewer');
        $user->roles->first()->update(['session_lifetime_minutes' => 60]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->get('/dashboard')->assertStatus(200); // seeds _last_activity_at

        // 50 minutes idle → within 60-minute lifetime
        $this->travel(50)->minutes();
        $this->get('/dashboard')->assertStatus(200);

        $this->travelBack();
    }

    public function test_user_with_custom_role_lifetime_expires_at_configured_threshold(): void
    {
        $user = $this->createUserWithRole('Viewer');
        $user->roles->first()->update(['session_lifetime_minutes' => 60]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->get('/dashboard')->assertStatus(200); // seeds _last_activity_at

        // 61 minutes idle → past the 60-minute role lifetime
        $this->travel(61)->minutes();
        $this->get('/dashboard')->assertRedirect('/login');

        $this->travelBack();
    }

    // ── Session timestamp is updated ───────────────────────────────────────

    public function test_session_last_activity_is_refreshed_on_each_request(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);

        // First request sets _last_activity_at
        $this->travel(10)->minutes();
        $this->get('/dashboard')->assertStatus(200);

        // Second request: only 10 more minutes — timestamp should have refreshed
        $this->travel(10)->minutes();
        $this->get('/dashboard')->assertStatus(200);

        $this->travelBack();
    }
}
