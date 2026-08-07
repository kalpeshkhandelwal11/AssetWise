<?php

namespace Tests\Feature\Admin;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WorkflowStepControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    private function workflow(): ApprovalWorkflow
    {
        return ApprovalWorkflow::factory()->module('transfer')->create();
    }

    public function test_store_requires_workflow_manage(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->createUserWithRole('Approver'))
             ->post(route('admin.workflows.steps.store', $workflow), [
                 'level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',
             ])
             ->assertForbidden();
    }

    public function test_store_adds_a_role_step(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $workflow), [
                 'level'            => 1,
                 'approver_type'    => 'role',
                 'approver_role'    => 'Approver',
                 'escalation_hours' => 48,
             ])
             ->assertRedirect(route('admin.workflows.edit', $workflow));

        $this->assertDatabaseHas('approval_steps', [
            'workflow_id'      => $workflow->id,
            'level'            => 1,
            'approver_type'    => 'role',
            'approver_role'    => 'Approver',
            'approver_user_id' => null,
            'escalation_hours' => 48,
        ]);
    }

    public function test_store_adds_a_user_step_and_nulls_the_role(): void
    {
        $workflow = $this->workflow();
        $user = User::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $workflow), [
                 'level'            => 1,
                 'approver_type'    => 'user',
                 'approver_role'    => 'Approver', // should be discarded
                 'approver_user_id' => $user->id,
             ]);

        $this->assertDatabaseHas('approval_steps', [
            'workflow_id'      => $workflow->id,
            'approver_type'    => 'user',
            'approver_role'    => null,
            'approver_user_id' => $user->id,
        ]);
    }

    public function test_level_must_be_unique_within_the_workflow(): void
    {
        $workflow = $this->workflow();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $workflow), [
                 'level' => 1, 'approver_type' => 'role', 'approver_role' => 'Asset Manager',
             ])
             ->assertSessionHasErrors('level');
    }

    public function test_the_same_level_is_allowed_in_a_different_workflow(): void
    {
        $first = $this->workflow();
        $second = ApprovalWorkflow::factory()->module('disposal')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $first->id]);

        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $second), [
                 'level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',
             ])
             ->assertSessionHasNoErrors();
    }

    public function test_role_is_required_when_approver_type_is_role(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $this->workflow()), [
                 'level' => 1, 'approver_type' => 'role',
             ])
             ->assertSessionHasErrors('approver_role');
    }

    public function test_user_is_required_when_approver_type_is_user(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $this->workflow()), [
                 'level' => 1, 'approver_type' => 'user',
             ])
             ->assertSessionHasErrors('approver_user_id');
    }

    public function test_role_must_exist(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.workflows.steps.store', $this->workflow()), [
                 'level' => 1, 'approver_type' => 'role', 'approver_role' => 'Wizard',
             ])
             ->assertSessionHasErrors('approver_role');
    }

    public function test_update_changes_the_step(): void
    {
        $workflow = $this->workflow();
        $step = ApprovalStep::factory()->forRole('Approver', 1, 48)->create(['workflow_id' => $workflow->id]);

        $this->actingAs($this->admin())
             ->put(route('admin.workflows.steps.update', [$workflow, $step]), [
                 'level'            => 2,
                 'approver_type'    => 'role',
                 'approver_role'    => 'Asset Manager',
                 'escalation_hours' => 24,
             ])
             ->assertRedirect(route('admin.workflows.edit', $workflow));

        $this->assertDatabaseHas('approval_steps', [
            'id'               => $step->id,
            'level'            => 2,
            'approver_role'    => 'Asset Manager',
            'escalation_hours' => 24,
        ]);
    }

    public function test_update_allows_keeping_the_same_level(): void
    {
        $workflow = $this->workflow();
        $step = ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        $this->actingAs($this->admin())
             ->put(route('admin.workflows.steps.update', [$workflow, $step]), [
                 'level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',
             ])
             ->assertSessionHasNoErrors();
    }

    public function test_a_step_from_another_workflow_is_not_reachable(): void
    {
        $workflow = $this->workflow();
        $foreign = ApprovalStep::factory()->forRole('Approver', 1)->create();

        $this->actingAs($this->admin())
             ->delete(route('admin.workflows.steps.destroy', [$workflow, $foreign]))
             ->assertNotFound();
    }

    public function test_destroy_removes_the_step(): void
    {
        $workflow = $this->workflow();
        $step = ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        $this->actingAs($this->admin())
             ->delete(route('admin.workflows.steps.destroy', [$workflow, $step]))
             ->assertRedirect(route('admin.workflows.edit', $workflow));

        $this->assertDatabaseMissing('approval_steps', ['id' => $step->id]);
    }
}
