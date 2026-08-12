<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_permission(): void
    {
        $user = $this->createUserWithRole('Viewer'); // no employees.manage

        $this->actingAs($user)
             ->get(route('admin.employees.index'))
             ->assertForbidden();
    }

    public function test_index_lists_employees(): void
    {
        Employee::factory()->create(['name' => 'Ravi Kumar', 'employee_code' => 'EMP-1']);

        $this->actingAs($this->admin())
             ->get(route('admin.employees.index'))
             ->assertOk()
             ->assertSee('Ravi Kumar');
    }

    public function test_create_and_edit_forms_render(): void
    {
        $this->actingAs($this->admin())
             ->get(route('admin.employees.create'))
             ->assertOk()
             ->assertSee('New Employee');

        $employee = Employee::factory()->create();
        $this->actingAs($this->admin())
             ->get(route('admin.employees.edit', $employee))
             ->assertOk()
             ->assertSee('Edit Employee');
    }

    public function test_store_creates_employee_without_a_user(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.employees.store'), [
                 'name'          => 'Anita Sharma',
                 'employee_code' => 'EMP-99',
             ])
             ->assertRedirect(route('admin.employees.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-99',
            'name'          => 'Anita Sharma',
            'user_id'       => null,
            'is_active'     => true,
        ]);
    }

    public function test_store_requires_unique_employee_code(): void
    {
        Employee::factory()->create(['employee_code' => 'DUP']);

        $this->actingAs($this->admin())
             ->post(route('admin.employees.store'), ['name' => 'X', 'employee_code' => 'DUP'])
             ->assertSessionHasErrors('employee_code');
    }

    public function test_a_user_can_only_link_to_one_employee(): void
    {
        $user = $this->createUserWithRole('Department User');
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())
             ->post(route('admin.employees.store'), [
                 'name'          => 'Dup Link',
                 'employee_code' => 'EMP-LINK',
                 'user_id'       => $user->id,
             ])
             ->assertSessionHasErrors('user_id');
    }

    public function test_cannot_deactivate_employee_holding_active_assets(): void
    {
        $employee = Employee::factory()->create(['is_active' => true]);
        Asset::factory()->create(['custodian_id' => $employee->id, 'is_active' => true]);

        $this->actingAs($this->admin())
             ->patch(route('admin.employees.toggle', $employee))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_can_deactivate_employee_after_assets_released(): void
    {
        $employee = Employee::factory()->create(['is_active' => true]);
        $asset = Asset::factory()->create(['custodian_id' => $employee->id, 'is_active' => true]);
        $asset->update(['custodian_id' => null]);

        $this->actingAs($this->admin())
             ->patch(route('admin.employees.toggle', $employee))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertFalse($employee->fresh()->is_active);
    }

    public function test_asset_custodian_resolves_to_employee(): void
    {
        $employee = Employee::factory()->create();
        $asset = Asset::factory()->create(['custodian_id' => $employee->id]);

        $this->assertInstanceOf(Employee::class, $asset->custodian);
        $this->assertTrue($asset->custodian->is($employee));
    }
}
