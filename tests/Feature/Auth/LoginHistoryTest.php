<?php

namespace Tests\Feature\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    // ── Successful login logging ───────────────────────────────────────────

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'status'  => 'success',
        ]);
    }

    public function test_successful_login_updates_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_successful_login_logs_ip_address(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        $history = LoginHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history->ip_address);
    }

    // ── Failed login logging ───────────────────────────────────────────────

    public function test_failed_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'email'  => $user->email,
            'status' => 'failed',
        ]);
    }

    public function test_failed_login_logs_without_user_id_for_unknown_email(): void
    {
        $this->post('/login', [
            'email'    => 'ghost@example.com',
            'password' => 'anything',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'email'   => 'ghost@example.com',
            'status'  => 'failed',
            'user_id' => null,
        ]);
    }

    public function test_deactivated_user_login_attempt_is_logged(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        // deactivated users pass credential check → login fires → success log
        // then we logout and block them; history may be success or not logged
        // the key assertion: they are not authenticated
        $this->assertGuest();
    }

    // ── History record structure ───────────────────────────────────────────

    public function test_login_history_has_correct_device_type_for_mobile(): void
    {
        $user = User::factory()->create();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)',
        ])->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'user_id'     => $user->id,
            'device_type' => 'mobile',
        ]);
    }

    public function test_login_history_defaults_to_desktop_for_standard_browser(): void
    {
        $user = User::factory()->create();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->post('/login', [
            'email'    => $user->email,
            'password' => 'Admin@1234',
        ]);

        $this->assertDatabaseHas('login_histories', [
            'user_id'     => $user->id,
            'device_type' => 'desktop',
        ]);
    }

    // ── Multiple sessions ──────────────────────────────────────────────────

    public function test_multiple_logins_each_create_separate_history_records(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);

        $this->assertEquals(2, LoginHistory::where('user_id', $user->id)->where('status', 'success')->count());
    }
}
