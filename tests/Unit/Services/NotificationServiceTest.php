<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_writes_a_database_notification_with_the_passed_in_type(): void
    {
        $user = User::factory()->create();

        app(NotificationService::class)->send($user, 'approval.pending', ['request_id' => 7]);

        $notification = $user->fresh()->notifications->first();

        $this->assertNotNull($notification);
        // The type column holds the app-level type, not App\Notifications\GenericNotification.
        $this->assertSame('approval.pending', $notification->type);
        $this->assertSame(7, $notification->data['request_id']);
        $this->assertNull($notification->read_at);
    }

    public function test_send_many_notifies_every_user(): void
    {
        $users = User::factory()->count(3)->create();

        app(NotificationService::class)->sendMany($users, 'approval.escalated', []);

        foreach ($users as $user) {
            $this->assertCount(1, $user->fresh()->notifications);
        }
    }
}
