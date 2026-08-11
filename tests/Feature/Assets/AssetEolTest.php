<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetEolTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    private function baseFields(Asset $asset): array
    {
        return [
            'name'          => $asset->name,
            'category_id'   => $asset->category_id,
            'asset_type_id' => $asset->asset_type_id,
            'status_id'     => $asset->status_id,
        ];
    }

    public function test_checking_the_eol_box_sets_is_eol_and_projected_date(): void
    {
        $asset = Asset::factory()->create(['is_eol' => false]);

        $this->actingAs($this->admin())->put(route('assets.update', $asset), $this->baseFields($asset) + [
            'is_eol'             => '1',
            'eol_projected_date' => now()->addYear()->toDateString(),
        ]);

        $asset->refresh();
        $this->assertTrue($asset->is_eol);
        $this->assertSame(now()->addYear()->toDateString(), $asset->eol_projected_date->toDateString());
    }

    public function test_unchecking_the_eol_box_clears_is_eol(): void
    {
        $asset = Asset::factory()->create(['is_eol' => true]);

        // Omitting is_eol simulates an unchecked checkbox — must still flip it back to false,
        // not silently leave the prior true value in place.
        $this->actingAs($this->admin())->put(route('assets.update', $asset), $this->baseFields($asset));

        $asset->refresh();
        $this->assertFalse($asset->is_eol);
    }
}
