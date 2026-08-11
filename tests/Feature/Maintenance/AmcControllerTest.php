<?php

namespace Tests\Feature\Maintenance;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AmcControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_index_requires_maintenance_manage_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('amc.index'))->assertForbidden();
    }

    public function test_store_creates_a_contract_and_syncs_asset_amc_expiry(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['amc_expiry' => null]);

        $this->actingAs($manager)->post(route('assets.amc.store', $asset), [
            'vendor'     => 'CoolTech AMC',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'cost'       => 4000,
        ])->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertNotNull($asset->amc_expiry);
        $this->assertSame(now()->addYear()->toDateString(), $asset->amc_expiry->toDateString());
    }

    public function test_amc_expiry_syncs_to_the_furthest_end_date_across_multiple_contracts(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();

        AmcContract::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->addMonths(3)]);
        $this->actingAs($manager)->post(route('assets.amc.store', $asset), [
            'vendor'     => 'Later Vendor',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonths(9)->toDateString(),
        ]);

        $asset->refresh();
        $this->assertSame(now()->addMonths(9)->toDateString(), $asset->amc_expiry->toDateString());
    }

    public function test_deleting_the_last_contract_clears_asset_amc_expiry(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $contract = AmcContract::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->addMonths(3)]);
        $asset->update(['amc_expiry' => $contract->end_date]);

        $this->actingAs($manager)->delete(route('assets.amc.destroy', [$asset, $contract]))
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertNull($asset->amc_expiry);
    }

    public function test_end_date_must_be_on_or_after_start_date(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('assets.amc.store', $asset), [
            'vendor'     => 'Bad Dates Vendor',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors('end_date');
    }
}
