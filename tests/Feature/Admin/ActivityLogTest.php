<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.activity-log.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($approver)
             ->get(route('admin.activity-log.index'))
             ->assertForbidden();
    }

    public function test_auditor_can_view_activity_log(): void
    {
        $auditor = $this->createUserWithRole('Auditor');

        $this->actingAs($auditor)
             ->get(route('admin.activity-log.index'))
             ->assertOk();
    }

    public function test_renders_row_whose_subject_was_soft_deleted(): void
    {
        $admin = $this->admin();
        $asset = Asset::factory()->create(['name' => 'Old Laptop']);
        $asset->update(['name' => 'Renamed Laptop']);
        $asset->delete();

        $this->actingAs($admin)
             ->get(route('admin.activity-log.index'))
             ->assertOk()
             ->assertSee('Asset #' . $asset->id);
    }
}
