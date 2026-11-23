<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Lists and reports must not crash when a row belongs to an asset that has since been deleted
 * (production 500s on /warranty, /amc and /maintenance: they built a link to a missing asset).
 */
class DeletedAssetLinksTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_pages_with_rows_for_a_deleted_asset_still_load(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $live = Asset::factory()->create(['name' => 'LIVE-PUMP']);
        $gone = Asset::factory()->create(['name' => 'GONE-PUMP']);
        $type = MaintenanceType::firstOrCreate(['code' => 'CORR'], ['name' => 'Corrective', 'is_active' => true]);

        foreach ([$live, $gone] as $asset) {
            MaintenanceRecord::factory()->create(['asset_id' => $asset->id, 'maintenance_type_id' => $type->id, 'status' => 'completed']);
            AssetMovement::factory()->create(['asset_id' => $asset->id, 'status' => 'completed']);
        }
        $gone->delete();

        // Maintenance list: loads, and leaves the deleted asset's record out.
        $this->actingAs($admin)->get(route('maintenance.index'))
            ->assertOk()->assertSee('LIVE-PUMP')->assertDontSee('GONE-PUMP');

        // Reports that list movements per asset render instead of erroring.
        foreach (['movement', 'intercompany_transfer', 'audit_compliance'] as $type) {
            $this->actingAs($admin)->get(route('reports.show', ['type' => $type]))->assertOk();
        }
    }
}
