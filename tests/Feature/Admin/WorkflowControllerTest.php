<?php

namespace Tests\Feature\Admin;

use App\Models\ApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.workflows.index'))->assertRedirect('/login');
    }

    public function test_index_requires_workflow_manage(): void
    {
        // Approver has workflow.approve but NOT workflow.manage.
        $this->actingAs($this->createUserWithRole('Approver'))
             ->get(route('admin.workflows.index'))
             ->assertForbidden();
    }

    public function test_index_lists_workflows(): void
    {
        ApprovalWorkflow::factory()->create(['name' => 'Transfer Chain', 'module' => 'transfer']);

        $this->actingAs($this->admin())
             ->get(route('admin.workflows.index'))
             ->assertOk()
             ->assertSee('Transfer Chain');
    }

    public function test_index_filters_by_module(): void
    {
        ApprovalWorkflow::factory()->module('transfer')->create(['name' => 'Transfer Chain']);
        ApprovalWorkflow::factory()->module('disposal')->create(['name' => 'Disposal Chain']);

        $this->actingAs($this->admin())
             ->get(route('admin.workflows.index', ['module' => 'disposal']))
             ->assertOk()
             ->assertSee('Disposal Chain')
             ->assertDontSee('Transfer Chain');
    }

    public function test_store_creates_a_workflow_and_redirects_to_its_steps(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.workflows.store'), [
                 'name'      => 'Kit Assignment Chain',
                 'module'    => 'kit_assignment',
                 'is_active' => '1',
             ])
             ->assertRedirect(route('admin.workflows.edit', ApprovalWorkflow::first()));

        $this->assertDatabaseHas('approval_workflows', [
            'name'      => 'Kit Assignment Chain',
            'module'    => 'kit_assignment',
            'is_active' => true,
        ]);
    }

    public function test_store_validates_the_module_enum(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.workflows.store'), ['name' => 'Bad', 'module' => 'nonsense'])
             ->assertSessionHasErrors('module');
    }

    public function test_activating_a_workflow_deactivates_its_module_siblings(): void
    {
        $existing = ApprovalWorkflow::factory()->module('transfer')->create();
        $other = ApprovalWorkflow::factory()->module('disposal')->create();
        $target = ApprovalWorkflow::factory()->module('transfer')->inactive()->create();

        $this->actingAs($this->admin())
             ->put(route('admin.workflows.update', $target), [
                 'name'      => $target->name,
                 'module'    => 'transfer',
                 'is_active' => '1',
             ])
             ->assertRedirect(route('admin.workflows.index'));

        $this->assertFalse($existing->fresh()->is_active);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_update_without_the_active_flag_deactivates_the_workflow(): void
    {
        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();

        $this->actingAs($this->admin())
             ->put(route('admin.workflows.update', $workflow), [
                 'name'   => 'Renamed',
                 'module' => 'transfer',
             ]);

        $this->assertFalse($workflow->fresh()->is_active);
        $this->assertSame('Renamed', $workflow->fresh()->name);
    }

    public function test_destroy_deactivates_instead_of_deleting(): void
    {
        $workflow = ApprovalWorkflow::factory()->create();

        $this->actingAs($this->admin())
             ->delete(route('admin.workflows.destroy', $workflow))
             ->assertRedirect(route('admin.workflows.index'));

        $this->assertDatabaseHas('approval_workflows', ['id' => $workflow->id, 'is_active' => false]);
    }

    public function test_edit_form_is_accessible(): void
    {
        $workflow = ApprovalWorkflow::factory()->create(['name' => 'Editable Chain']);

        $this->actingAs($this->admin())
             ->get(route('admin.workflows.edit', $workflow))
             ->assertOk()
             ->assertSee('Editable Chain')
             ->assertSee('Approval Steps');
    }
}
