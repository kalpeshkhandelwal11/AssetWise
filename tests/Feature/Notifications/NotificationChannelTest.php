<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\GenericMailNotification;
use App\Notifications\GenericNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_always_dispatches_the_database_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        app(NotificationService::class)->send($user, 'approval.pending', ['url' => 'https://app.test/x']);

        Notification::assertSentTo($user, GenericNotification::class);
    }

    public function test_send_dispatches_mail_when_the_catalog_allows_it_and_the_user_has_not_opted_out(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'approver@example.test']);

        app(NotificationService::class)->send($user, 'approval.pending', ['url' => 'https://app.test/x']);

        Notification::assertSentTo($user, GenericMailNotification::class);
    }

    public function test_send_does_not_dispatch_mail_when_the_user_has_opted_out(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id'       => $user->id,
            'type'          => 'approval.pending',
            'email_enabled' => false,
        ]);

        app(NotificationService::class)->send($user, 'approval.pending', ['url' => 'https://app.test/x']);

        Notification::assertSentTo($user, GenericNotification::class);
        Notification::assertNotSentTo($user, GenericMailNotification::class);
    }

    public function test_send_does_not_dispatch_mail_when_the_catalog_omits_the_mail_channel(): void
    {
        Notification::fake();
        // Mutated via a full array replace, not a dotted config() path — the catalog's own
        // type keys ("approval.pending") contain literal dots, which collide with config()'s
        // dot-notation nesting if you try to target one key with a dotted string.
        $types = config('notifications.types');
        $types['approval.pending']['channels'] = ['database'];
        config(['notifications.types' => $types]);
        $user = User::factory()->create();

        app(NotificationService::class)->send($user, 'approval.pending', ['url' => 'https://app.test/x']);

        Notification::assertSentTo($user, GenericNotification::class);
        Notification::assertNotSentTo($user, GenericMailNotification::class);
    }

    public function test_send_many_respects_per_user_preferences_within_the_same_batch(): void
    {
        Notification::fake();
        $wantsMail = User::factory()->create();
        $optedOut = User::factory()->create();
        NotificationPreference::create([
            'user_id'       => $optedOut->id,
            'type'          => 'approval.escalated',
            'email_enabled' => false,
        ]);

        app(NotificationService::class)->sendMany(
            collect([$wantsMail, $optedOut]),
            'approval.escalated',
            ['url' => 'https://app.test/y'],
        );

        Notification::assertSentTo($wantsMail, GenericMailNotification::class);
        Notification::assertNotSentTo($optedOut, GenericMailNotification::class);
        Notification::assertSentTo($wantsMail, GenericNotification::class);
        Notification::assertSentTo($optedOut, GenericNotification::class);
    }

    public function test_send_does_not_dispatch_mail_when_the_user_has_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => '']);

        app(NotificationService::class)->send($user, 'approval.pending', ['url' => 'https://app.test/x']);

        Notification::assertNotSentTo($user, GenericMailNotification::class);
    }

    /**
     * GenericNotification must stay database-only and NOT ShouldQueue: if it queued, the
     * bell row would be written by the queue worker rather than inline, and a dev
     * environment with no `queue:work` running would never show the notification at all.
     * GenericMailNotification is the one that should queue.
     */
    public function test_only_the_mail_notification_implements_should_queue(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new GenericNotification('t', []));
        $this->assertInstanceOf(ShouldQueue::class, new GenericMailNotification('t', []));
    }
}
