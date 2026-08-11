<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ReportPermissionTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_index_requires_reports_view_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_index_is_accessible_to_a_role_with_reports_view(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
    }

    public function test_show_requires_reports_view_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.show', 'asset_register'))->assertForbidden();
    }

    public function test_show_is_accessible_to_a_role_with_reports_view(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->get(route('reports.show', 'asset_register'))->assertOk();
    }

    public function test_export_requires_reports_export_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo('reports.view');

        $this->actingAs($user)->post(route('reports.export', 'asset_register'))->assertForbidden();
    }

    public function test_export_is_accessible_to_a_role_with_reports_export(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->post(route('reports.export', 'asset_register'))->assertOk();
    }
}
