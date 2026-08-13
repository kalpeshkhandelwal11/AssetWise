<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('assets.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create(); // no permissions

        $this->actingAs($user)->get(route('assets.index'))->assertForbidden();
    }

    public function test_index_lists_assets(): void
    {
        Asset::factory()->create(['name' => 'Dell Laptop']);

        $this->actingAs($this->admin())
             ->get(route('assets.index'))
             ->assertOk()
             ->assertSee('Dell Laptop');
    }

    public function test_index_per_page_option(): void
    {
        Asset::factory()->count(3)->create();

        $this->actingAs($this->admin())
             ->get(route('assets.index', ['per_page' => 50]))
             ->assertViewHas('assets', fn ($p) => $p->perPage() === 50);

        // An out-of-whitelist value falls back to the 20 default.
        $this->actingAs($this->admin())
             ->get(route('assets.index', ['per_page' => 999]))
             ->assertViewHas('assets', fn ($p) => $p->perPage() === 20);
    }

    public function test_index_sort_by_name(): void
    {
        Asset::factory()->create(['name' => 'Zeta Rig']);
        Asset::factory()->create(['name' => 'Alpha Rig']);

        $this->actingAs($this->admin())
             ->get(route('assets.index', ['sort' => 'name']))
             ->assertViewHas('assets', fn ($p) => $p->first()->name === 'Alpha Rig');
    }

    public function test_index_filters_by_search(): void
    {
        Asset::factory()->create(['name' => 'Dell Laptop']);
        Asset::factory()->create(['name' => 'HP Printer']);

        $this->actingAs($this->admin())
             ->get(route('assets.index', ['search' => 'Dell']))
             ->assertOk()
             ->assertSee('Dell Laptop')
             ->assertDontSee('HP Printer');
    }

    public function test_index_filters_by_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Asset::factory()->create(['name' => 'Asset A', 'company_id' => $companyA->id]);
        Asset::factory()->create(['name' => 'Asset B', 'company_id' => $companyB->id]);

        $this->actingAs($this->admin())
             ->get(route('assets.index', ['company_id' => $companyA->id]))
             ->assertOk()
             ->assertSee('Asset A')
             ->assertDontSee('Asset B');
    }

    public function test_index_excludes_soft_deleted_by_default(): void
    {
        $asset = Asset::factory()->create(['name' => 'Deleted Asset']);
        $asset->delete();

        $this->actingAs($this->admin())
             ->get(route('assets.index'))
             ->assertOk()
             ->assertDontSee('Deleted Asset');
    }

    public function test_index_shows_soft_deleted_with_flag_for_authorized_user(): void
    {
        $asset = Asset::factory()->create(['name' => 'Deleted Asset']);
        $asset->delete();

        $this->actingAs($this->admin())
             ->get(route('assets.index', ['show_deleted' => 1]))
             ->assertOk()
             ->assertSee('Deleted Asset');
    }

    public function test_create_form_is_accessible(): void
    {
        $this->actingAs($this->admin())
             ->get(route('assets.create'))
             ->assertOk()
             ->assertSee('New Asset');
    }

    public function test_store_creates_asset_with_creator(): void
    {
        $admin = $this->admin();
        $company = Company::factory()->create();
        $category = AssetCategory::factory()->create();
        $type = \App\Models\AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = \App\Models\AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);

        $this->actingAs($admin)
             ->post(route('assets.store'), [
                 'name' => 'New Laptop',
                 'company_id' => $company->id,
                 'category_id' => $category->id,
                 'asset_type_id' => $type->id,
                 'status_id' => $status->id,
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'name' => 'New Laptop',
            'company_id' => $company->id,
            'created_by' => $admin->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
             ->post(route('assets.store'), [])
             ->assertSessionHasErrors(['name', 'company_id', 'category_id', 'asset_type_id', 'status_id']);
    }

    public function test_show_displays_asset(): void
    {
        $asset = Asset::factory()->create(['name' => 'Server Rack']);

        $this->actingAs($this->admin())
             ->get(route('assets.show', $asset))
             ->assertOk()
             ->assertSee('Server Rack');
    }

    public function test_update_saves_changes(): void
    {
        $asset = Asset::factory()->create(['name' => 'Old Name']);
        $admin = $this->admin();

        $this->actingAs($admin)
             ->put(route('assets.update', $asset), [
                 'name' => 'New Name',
                 'category_id' => $asset->category_id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id' => $asset->status_id,
             ])
             ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame('New Name', $asset->name);
        $this->assertSame($admin->id, $asset->updated_by);
    }

    public function test_update_rejects_company_change_without_companies_manage(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo(['assets.view', 'assets.edit']);

        $originalCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $originalCompany->id]);

        $this->actingAs($user)
             ->put(route('assets.update', $asset), [
                 'name' => $asset->name,
                 'company_id' => $otherCompany->id,
                 'category_id' => $asset->category_id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id' => $asset->status_id,
             ])
             ->assertSessionHasErrors('company_id');

        $this->assertSame($originalCompany->id, $asset->refresh()->company_id);
    }

    public function test_update_without_touching_company_field_succeeds_for_standard_user(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo(['assets.view', 'assets.edit']);

        $asset = Asset::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
             ->put(route('assets.update', $asset), [
                 'name' => 'New Name',
                 'category_id' => $asset->category_id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id' => $asset->status_id,
             ])
             ->assertRedirect(route('assets.show', $asset));

        $this->assertSame('New Name', $asset->refresh()->name);
    }

    public function test_update_allows_company_change_with_companies_manage(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo(['assets.view', 'assets.edit', 'companies.manage']);

        $otherCompany = Company::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($user)
             ->put(route('assets.update', $asset), [
                 'name' => $asset->name,
                 'company_id' => $otherCompany->id,
                 'category_id' => $asset->category_id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id' => $asset->status_id,
             ]);

        $this->assertSame($otherCompany->id, $asset->refresh()->company_id);
    }

    public function test_destroy_soft_deletes_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->delete(route('assets.destroy', $asset))
             ->assertRedirect(route('assets.index'));

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_update_logs_activity(): void
    {
        $asset = Asset::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin())
             ->put(route('assets.update', $asset), [
                 'name' => 'Updated Name',
                 'category_id' => $asset->category_id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id' => $asset->status_id,
             ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Asset::class,
            'subject_id'   => $asset->id,
            'event'        => 'updated',
        ]);
    }
}
