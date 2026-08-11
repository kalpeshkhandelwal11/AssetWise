<?php

namespace Tests\Feature\Maintenance;

use App\Models\Asset;
use App\Models\User;
use App\Models\WarrantyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WarrantyControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_index_requires_maintenance_manage_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('warranty.index'))->assertForbidden();
    }

    public function test_store_creates_a_record_and_syncs_asset_warranty_expiry(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['warranty_expiry' => null]);

        $this->actingAs($manager)->post(route('assets.warranty.store', $asset), [
            'provider' => 'Manufacturer Inc',
            'end_date' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame(now()->addYear()->toDateString(), $asset->warranty_expiry->toDateString());
    }

    public function test_supports_multiple_warranty_records_per_asset_and_syncs_to_the_furthest(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();

        WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->addMonths(2)]);
        $this->actingAs($manager)->post(route('assets.warranty.store', $asset), [
            'provider' => 'Extended Warranty Co',
            'end_date' => now()->addYears(2)->toDateString(),
        ]);

        $this->assertSame(2, WarrantyRecord::where('asset_id', $asset->id)->count());
        $asset->refresh();
        $this->assertSame(now()->addYears(2)->toDateString(), $asset->warranty_expiry->toDateString());
    }

    public function test_deleting_the_last_record_clears_asset_warranty_expiry(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $record = WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->addYear()]);
        $asset->update(['warranty_expiry' => $record->end_date]);

        $this->actingAs($manager)->delete(route('assets.warranty.destroy', [$asset, $record]));

        $asset->refresh();
        $this->assertNull($asset->warranty_expiry);
    }
}
