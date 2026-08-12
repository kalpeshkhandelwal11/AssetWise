<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_rows_wants_email_for_every_catalog_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->wantsEmailFor('approval.pending'));
    }

    public function test_saving_a_non_default_choice_writes_a_sparse_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.notifications.update'), [
            'email_enabled' => ['approval.approved', 'movement.completed'],
        ])->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id'       => $user->id,
            'type'          => 'approval.pending',
            'email_enabled' => false,
        ]);
        // Types included in the submitted array match the default (mail-on) and get no row.
        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'type'    => 'approval.approved',
        ]);

        $this->assertFalse($user->wantsEmailFor('approval.pending'));
        $this->assertTrue($user->wantsEmailFor('approval.approved'));
    }

    public function test_reverting_to_default_deletes_the_row(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id'       => $user->id,
            'type'          => 'approval.pending',
            'email_enabled' => false,
        ]);

        $this->actingAs($user)->patch(route('profile.notifications.update'), [
            'email_enabled' => array_keys(config('notifications.types')),
        ]);

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'type'    => 'approval.pending',
        ]);
    }
}
