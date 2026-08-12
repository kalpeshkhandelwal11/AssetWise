<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get(route('notifications.index'))->assertRedirect('/login');
    }

    public function test_index_only_shows_the_authenticated_users_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        app(NotificationService::class)->send($user, 'export_ready', ['file_name' => 'mine.xlsx', 'download_url' => 'https://x.test/mine.xlsx', 'row_count' => 1]);
        app(NotificationService::class)->send($other, 'export_ready', ['file_name' => 'theirs.xlsx', 'download_url' => 'https://x.test/theirs.xlsx', 'row_count' => 1]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('mine.xlsx');
        $response->assertDontSee('theirs.xlsx');
    }

    public function test_marking_another_users_notification_read_404s(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        app(NotificationService::class)->send($owner, 'export_ready', ['file_name' => 'a.xlsx', 'download_url' => 'https://x.test/a.xlsx', 'row_count' => 1]);
        $notification = $owner->fresh()->notifications->first();

        $this->actingAs($intruder)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_read_marks_read_and_redirects_to_the_resolved_link(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'export_ready', [
            'file_name'    => 'a.xlsx',
            'download_url' => 'https://files.test/a.xlsx',
            'row_count'    => 1,
        ]);
        $notification = $user->fresh()->notifications->first();

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect('https://files.test/a.xlsx');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_falls_back_to_the_index_when_the_type_has_no_link(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'import_completed', ['batch_id' => null, 'error' => 'boom']);
        $notification = $user->fresh()->notifications->first();

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_read_all_clears_every_unread_notification_for_the_user(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->sendMany(collect([$user]), 'approval.pending', ['url' => 'https://x.test']);
        app(NotificationService::class)->sendMany(collect([$user]), 'approval.escalated', ['url' => 'https://x.test']);

        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
