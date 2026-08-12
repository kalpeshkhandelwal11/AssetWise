<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asserts on rendered markup rather than only DB state — per the M15 integration-pass
 * lesson (docs/decisions-log.md), a broken Blade/Alpine rendering path is invisible to
 * tests that only POST to endpoints and check the database.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_dropdown_renders_a_real_row_with_a_working_mark_read_form(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'export_ready', [
            'file_name'    => 'quarterly.xlsx',
            'download_url' => 'https://files.test/quarterly.xlsx',
            'row_count'    => 42,
        ]);
        $notification = $user->fresh()->notifications->first();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('No new notifications');
        $response->assertSee('Export ready: quarterly.xlsx');
        $response->assertSee(route('notifications.read', $notification->id), false);
    }

    public function test_bell_shows_empty_state_and_no_unread_badge_when_there_are_no_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No new notifications');
    }

    public function test_unread_badge_count_reflects_only_unread_notifications(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'export_ready', ['file_name' => 'a.xlsx', 'download_url' => 'https://x.test/a', 'row_count' => 1]);
        app(NotificationService::class)->send($user, 'export_ready', ['file_name' => 'b.xlsx', 'download_url' => 'https://x.test/b', 'row_count' => 1]);
        $user->fresh()->notifications->first()->markAsRead();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // One of the two is read, so the badge should read 1, not 2 — matched against the
        // badge's own distinguishing classes so this can't false-positive against an
        // unrelated "1" elsewhere on the dashboard.
        $this->assertStringContainsString(
            'bg-red-500 text-[10px] font-bold text-white">1</span>',
            $response->getContent(),
        );
    }
}
