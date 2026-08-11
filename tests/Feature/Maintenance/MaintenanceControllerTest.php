<?php

namespace Tests\Feature\Maintenance;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function statuses(): array
    {
        return [
            'available'   => AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]),
            'maintenance' => AssetStatus::create(['name' => 'In Maintenance', 'code' => 'MAINTENANCE', 'color' => '#f59e0b', 'is_system' => true, 'is_active' => true]),
        ];
    }

    public function test_index_requires_maintenance_manage_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('maintenance.index'))->assertForbidden();
    }

    public function test_index_is_accessible_to_asset_manager(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)->get(route('maintenance.index'))->assertOk();
    }

    public function test_store_logs_a_maintenance_record(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $type = MaintenanceType::create(['name' => 'Preventive', 'code' => 'PREVENTIVE', 'is_active' => true]);

        $response = $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), [
            'maintenance_type_id' => $type->id,
            'status'              => 'scheduled',
            'scheduled_date'      => now()->addWeek()->toDateString(),
            'vendor'              => 'Acme Repairs',
            'cost'                => 150.5,
        ]);

        $response->assertRedirect(route('assets.show', $asset));
        $this->assertDatabaseHas('maintenance_records', [
            'asset_id' => $asset->id, 'vendor' => 'Acme Repairs', 'status' => 'scheduled',
        ]);
    }

    public function test_starting_a_record_flips_asset_status_to_maintenance_and_completing_restores_it(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $status = $this->statuses();
        $asset = Asset::factory()->create(['status_id' => $status['available']->id]);
        $type = MaintenanceType::create(['name' => 'Corrective', 'code' => 'CORRECTIVE', 'is_active' => true]);

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), [
            'maintenance_type_id' => $type->id,
            'status'              => 'in_progress',
        ]);

        $asset->refresh();
        $this->assertSame($status['maintenance']->id, $asset->status_id);

        $record = MaintenanceRecord::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame($status['available']->id, $record->previous_status_id);

        $this->actingAs($manager)->put(route('assets.maintenance.update', [$asset, $record]), [
            'maintenance_type_id' => $type->id,
            'status'              => 'completed',
            'performed_date'      => now()->toDateString(),
        ]);

        $asset->refresh();
        $this->assertSame($status['available']->id, $asset->status_id);
    }

    public function test_cancelling_an_in_progress_record_also_restores_asset_status(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $status = $this->statuses();
        $asset = Asset::factory()->create(['status_id' => $status['available']->id]);
        $type = MaintenanceType::create(['name' => 'Corrective', 'code' => 'CORRECTIVE', 'is_active' => true]);

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), [
            'maintenance_type_id' => $type->id,
            'status'              => 'in_progress',
        ]);
        $asset->refresh();
        $record = MaintenanceRecord::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($manager)->put(route('assets.maintenance.update', [$asset, $record]), [
            'maintenance_type_id' => $type->id,
            'status'              => 'cancelled',
        ]);

        $asset->refresh();
        $this->assertSame($status['available']->id, $asset->status_id);
    }

    public function test_scheduled_record_does_not_touch_asset_status(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $status = $this->statuses();
        $asset = Asset::factory()->create(['status_id' => $status['available']->id]);
        $type = MaintenanceType::create(['name' => 'Preventive', 'code' => 'PREVENTIVE', 'is_active' => true]);

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), [
            'maintenance_type_id' => $type->id,
            'status'              => 'scheduled',
        ]);

        $asset->refresh();
        $this->assertSame($status['available']->id, $asset->status_id);
    }

    public function test_cannot_delete_an_in_progress_record(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $this->statuses();
        $asset = Asset::factory()->create();
        $record = MaintenanceRecord::factory()->create(['asset_id' => $asset->id, 'status' => 'in_progress']);

        $this->actingAs($manager)
            ->delete(route('assets.maintenance.destroy', [$asset, $record]))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('maintenance_records', ['id' => $record->id]);
    }
}
