<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    // ── Redirect enforcement ───────────────────────────────────────────────

    public function test_user_with_must_change_password_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.force-change'));
    }

    public function test_user_with_must_change_password_is_redirected_from_profile(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertRedirect(route('password.force-change'));
    }

    public function test_user_without_flag_can_access_dashboard(): void
    {
        $user = User::factory()->create(); // must_change_password = false

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_force_change_page_requires_auth(): void
    {
        $this->get(route('password.force-change'))
            ->assertRedirect('/login');
    }

    public function test_force_change_page_renders_for_auth_user(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get(route('password.force-change'))
            ->assertStatus(200);
    }

    // ── Validation ─────────────────────────────────────────────────────────

    public function test_password_change_fails_if_too_short(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'Short1!',
                'password_confirmation' => 'Short1!',
            ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_password_change_fails_without_uppercase(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'nouppercase1!',
                'password_confirmation' => 'nouppercase1!',
            ])->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_without_number(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'NoNumber!!',
                'password_confirmation' => 'NoNumber!!',
            ])->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_without_symbol(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'NoSymbol123',
                'password_confirmation' => 'NoSymbol123',
            ])->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_confirmation_mismatch(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'Valid@123',
                'password_confirmation' => 'Different@456',
            ])->assertSessionHasErrors('password');
    }

    // ── Successful change ──────────────────────────────────────────────────

    public function test_valid_password_clears_flag_and_redirects(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'password'              => 'NewStrong@99',
                'password_confirmation' => 'NewStrong@99',
            ])->assertRedirect(route('dashboard'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertNotNull($fresh->password_changed_at);
    }

    public function test_user_can_access_dashboard_after_password_change(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->put(route('password.force-change.update'), [
            'password'              => 'NewStrong@99',
            'password_confirmation' => 'NewStrong@99',
        ]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertStatus(200);
    }
}
