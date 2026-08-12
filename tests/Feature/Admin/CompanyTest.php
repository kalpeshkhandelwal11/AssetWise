<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.companies.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        $user = $this->createUserWithRole('Viewer'); // no companies.manage
        $this->actingAs($user)
             ->get(route('admin.companies.index'))
             ->assertForbidden();
    }

    public function test_index_lists_companies(): void
    {
        Company::factory()->create(['name' => 'ACME Corp', 'code' => 'ACME']);

        $this->actingAs($this->admin())
             ->get(route('admin.companies.index'))
             ->assertOk()
             ->assertSee('ACME Corp');
    }

    public function test_index_filters_by_search(): void
    {
        Company::factory()->create(['name' => 'Alpha Inc', 'code' => 'ALPHA']);
        Company::factory()->create(['name' => 'Beta Ltd', 'code' => 'BETA']);

        $this->actingAs($this->admin())
             ->get(route('admin.companies.index', ['search' => 'Alpha']))
             ->assertOk()
             ->assertSee('Alpha Inc')
             ->assertDontSee('Beta Ltd');
    }

    public function test_create_form_is_accessible(): void
    {
        $this->actingAs($this->admin())
             ->get(route('admin.companies.create'))
             ->assertOk()
             ->assertSee('New Company');
    }

    public function test_store_creates_company(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), [
                 'name' => 'Test Co',
                 'code' => 'TESTCO',
             ])
             ->assertRedirect(route('admin.companies.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', ['code' => 'TESTCO', 'name' => 'Test Co']);
    }

    public function test_store_uppercases_code(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), ['name' => 'Test Co', 'code' => 'testco']);

        $this->assertDatabaseHas('companies', ['code' => 'TESTCO']);
    }

    public function test_store_requires_unique_code(): void
    {
        Company::factory()->create(['code' => 'DUP']);

        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), ['name' => 'Another', 'code' => 'DUP'])
             ->assertSessionHasErrors('code');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), [])
             ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_edit_form_is_accessible(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin())
             ->get(route('admin.companies.edit', $company))
             ->assertOk()
             ->assertSee('Edit Company');
    }

    public function test_update_saves_changes(): void
    {
        $company = Company::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

        $this->actingAs($this->admin())
             ->put(route('admin.companies.update', $company), [
                 'name' => 'New Name',
                 'code' => 'NEW',
             ])
             ->assertRedirect(route('admin.companies.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'New Name', 'code' => 'NEW']);
    }

    public function test_toggle_deactivates_active_company(): void
    {
        $company = Company::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
             ->patch(route('admin.companies.toggle', $company));

        $this->assertFalse($company->refresh()->is_active);
    }

    public function test_toggle_activates_inactive_company(): void
    {
        $company = Company::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
             ->patch(route('admin.companies.toggle', $company));

        $this->assertTrue($company->refresh()->is_active);
    }

    public function test_destroy_deactivates_company(): void
    {
        $company = Company::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
             ->delete(route('admin.companies.destroy', $company))
             ->assertRedirect(route('admin.companies.index'));

        $this->assertFalse($company->refresh()->is_active);
    }

    public function test_cannot_deactivate_company_with_assets(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        Asset::factory()->create(['company_id' => $company->id]);

        $this->actingAs($this->admin())
             ->patch(route('admin.companies.toggle', $company))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($company->refresh()->is_active);
    }

    public function test_can_deactivate_company_after_assets_reassigned(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        $otherCompany = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id]);

        $asset->update(['company_id' => $otherCompany->id]);

        $this->actingAs($this->admin())
             ->patch(route('admin.companies.toggle', $company))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertFalse($company->refresh()->is_active);
    }

    public function test_cannot_destroy_company_with_assets(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        Asset::factory()->create(['company_id' => $company->id]);

        $this->actingAs($this->admin())
             ->delete(route('admin.companies.destroy', $company))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($company->refresh()->is_active);
    }

    // --- Company Info: hierarchy + legal/tax + registered address ---

    public function test_store_persists_company_info_fields(): void
    {
        $parent = Company::factory()->create(['code' => 'PARENT']);

        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), [
                 'name'               => 'Subsidiary Co',
                 'legal_name'         => 'Subsidiary Co Private Limited',
                 'code'               => 'SUBCO',
                 'parent_company_id'  => $parent->id,
                 'is_head_office'     => '1',
                 'gstin'              => '27AAAAA0000A1Z5',
                 'pan'                => 'AAAAA0000A',
                 'cin'                => 'U00000MH2020PTC000000',
                 'registered_address' => '1 Reg Street',
                 'registered_city'    => 'Mumbai',
                 'registered_state'   => 'Maharashtra',
                 'registered_pincode' => '400001',
                 'registered_country' => 'India',
             ])
             ->assertRedirect(route('admin.companies.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'code'              => 'SUBCO',
            'legal_name'        => 'Subsidiary Co Private Limited',
            'parent_company_id' => $parent->id,
            'is_head_office'    => true,
            'gstin'             => '27AAAAA0000A1Z5',
            'registered_state'  => 'Maharashtra',
        ]);
    }

    public function test_unchecked_head_office_stores_false(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), ['name' => 'Plain Co', 'code' => 'PLAIN']);

        $this->assertDatabaseHas('companies', ['code' => 'PLAIN', 'is_head_office' => false]);
    }

    public function test_company_cannot_be_its_own_parent(): void
    {
        $company = Company::factory()->create(['code' => 'SELF']);

        $this->actingAs($this->admin())
             ->put(route('admin.companies.update', $company), [
                 'name'              => 'Self Co',
                 'code'              => 'SELF',
                 'parent_company_id' => $company->id,
             ])
             ->assertSessionHasErrors('parent_company_id');

        $this->assertNull($company->refresh()->parent_company_id);
    }

    public function test_store_rejects_bad_length_tax_ids(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.companies.store'), [
                 'name'  => 'Bad Tax Co',
                 'code'  => 'BADTAX',
                 'gstin' => 'TOOSHORT',
                 'pan'   => 'X',
             ])
             ->assertSessionHasErrors(['gstin', 'pan']);
    }

    public function test_parent_company_relationship_resolves(): void
    {
        $parent = Company::factory()->create(['code' => 'HOLDCO']);
        $child = Company::factory()->create(['code' => 'CHILDCO', 'parent_company_id' => $parent->id]);

        $this->assertTrue($child->parentCompany->is($parent));
        $this->assertTrue($parent->childCompanies->contains($child));
    }
}
