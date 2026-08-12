<?php

namespace Tests\Feature\Notifications;

use App\Notifications\GenericMailNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GenericMailNotification is ShouldQueue, so toMail() only ever runs once the queue worker
 * has picked the job up — outside the original HTTP request, where route() still resolves
 * correctly only because APP_URL is configured and the payload already stored a fully
 * resolved absolute URL at send() time. This is the classic way this class of bug ships:
 * a relative link that "works" in every manual test run from the same request.
 */
class NotificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_mail_contains_the_absolute_url_from_the_payload(): void
    {
        $user = User::factory()->create();
        $notification = new GenericMailNotification('approval.pending', [
            'workflow'   => 'Transfer Approval',
            'subject'    => 'Asset #12',
            'step_level' => 1,
            'url'        => 'http://assetwise.test/approvals/99',
        ]);

        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        $this->assertStringContainsString('http://assetwise.test/approvals/99', $rendered);
        $this->assertStringContainsString('Your approval is needed at step 1.', $rendered);
        $this->assertSame('Transfer Approval — Asset #12', $mail->subject);
    }

    public function test_rendered_mail_omits_the_action_button_when_the_type_has_no_link(): void
    {
        $user = User::factory()->create();
        $notification = new GenericMailNotification('import_completed', [
            'batch_id' => null,
            'error'    => 'The uploaded file could not be read.',
        ]);

        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        $this->assertStringContainsString('The uploaded file could not be read.', $rendered);
        $this->assertNull($mail->actionUrl ?? null);
    }
}
