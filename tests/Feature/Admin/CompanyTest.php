<?php

namespace Tests\Feature\Admin;

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
}
