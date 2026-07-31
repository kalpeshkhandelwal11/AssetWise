<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_notice_redirects_already_verified_user(): void
    {
        $user = User::factory()->create(); // email_verified_at is set by default

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertRedirect('/dashboard');
    }

    public function test_resend_verification_notification_requires_auth(): void
    {
        $this->post('/email/verification-notification')
            ->assertRedirect('/login');
    }

    public function test_resend_verification_notification_is_sent(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_already_verified_user_is_redirected_on_resend(): void
    {
        $user = User::factory()->create(); // already verified

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect('/dashboard');
    }
}
