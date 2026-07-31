<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token'                 => $notification->token,
                'email'                 => $user->email,
                'password'              => 'Admin@1234',
                'password_confirmation' => 'Admin@1234',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function test_reset_fails_with_weak_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token'                 => $notification->token,
                'email'                 => $user->email,
                'password'              => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasErrors('password');

            return true;
        });
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/reset-password', [
            'token'                 => 'invalid-token-xyz',
            'email'                 => $user->email,
            'password'              => 'Admin@1234',
            'password_confirmation' => 'Admin@1234',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_returns_error_for_unknown_email(): void
    {
        // Laravel's default PasswordResetLinkController returns a validation
        // error for unknown emails (it does not implement user-enumeration protection).
        $response = $this->post('/forgot-password', [
            'email' => 'nobody@nowhere.test',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
