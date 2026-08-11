<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DashboardCompanyFilterTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_company_filter_scopes_asset_counts(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Asset::factory()->count(2)->create(['company_id' => $companyA->id]);
        Asset::factory()->count(5)->create(['company_id' => $companyB->id]);

        $unscoped = $this->actingAs($admin)->get(route('dashboard'));
        $unscoped->assertOk();
        $unscoped->assertViewHas('totalAssets', 7);

        $scoped = $this->actingAs($admin)->get(route('dashboard', ['company_id' => $companyA->id]));
        $scoped->assertOk();
        $scoped->assertViewHas('totalAssets', 2);
    }

    public function test_available_tags_stays_global_regardless_of_company_filter(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $company = Company::factory()->create();
        Tag::factory()->count(3)->create(['status' => 'available']);

        $response = $this->actingAs($admin)->get(route('dashboard', ['company_id' => $company->id]));

        $response->assertOk();
        $response->assertViewHas('availableTags', 3);
    }

    public function test_recent_movements_are_scoped_to_the_selected_company(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        \App\Models\AssetMovement::factory()->create(['from_company_id' => $companyA->id]);
        \App\Models\AssetMovement::factory()->create(['from_company_id' => $companyB->id]);

        $response = $this->actingAs($admin)->get(route('dashboard', ['company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertViewHas('recentMovements', fn ($movements) => $movements->count() === 1);
    }
}
