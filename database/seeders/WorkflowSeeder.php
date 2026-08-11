<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Seeder;

/**
 * Default approval workflows (M08).
 *
 * Runs after RolePermissionSeeder — steps reference Spatie role *names*, which must exist.
 *
 * `disposal` and `kit_assignment` intentionally get no default workflow: M13/M17 (or an
 * admin, via /admin/workflows) configure them later, which is itself the proof that the
 * "configurable without code changes" acceptance criterion holds.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWorkflow('Standard Transfer Approval', 'transfer', [
            // The spec calls for "Dept Head -> Asset Manager". No department-head field
            // exists anywhere in the schema yet (no department_id on users, no
            // head_user_id on departments), so level 1 routes to the closest existing
            // role, "Approver". Swap to approver_type=user once M01 grows dept heads.
            ['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',      'escalation_hours' => 48],
            ['level' => 2, 'approver_type' => 'role', 'approver_role' => 'Asset Manager', 'escalation_hours' => 48],
        ]);

        $this->seedWorkflow('Standard Tag Replacement Approval', 'tag_replacement', [
            ['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Asset Manager', 'escalation_hours' => 48],
            ['level' => 2, 'approver_type' => 'role', 'approver_role' => 'Super Admin',   'escalation_hours' => 48],
        ]);

        // M16: depreciation-settings changes (method/life/salvage, capitalization) are
        // approval-gated. Asset Manager submits, so the step routes to Approver to keep
        // submitter and approver distinct — same rationale as the transfer workflow.
        $this->seedWorkflow('Standard Depreciation Approval', 'depreciation', [
            ['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver', 'escalation_hours' => 48],
        ]);
    }

    private function seedWorkflow(string $name, string $module, array $steps): void
    {
        $workflow = ApprovalWorkflow::firstOrCreate(
            ['module' => $module, 'name' => $name],
            ['is_active' => true],
        );

        foreach ($steps as $step) {
            $workflow->steps()->updateOrCreate(
                ['level' => $step['level']],
                $step,
            );
        }
    }
}
