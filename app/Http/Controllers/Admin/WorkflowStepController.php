<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Nested under a workflow, one step per request — mirrors M04's CategoryFieldController.
 */
class WorkflowStepController extends Controller
{
    public function store(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('workflow.manage');

        $data = $this->validated($request, $workflow);

        $workflow->steps()->create($data);

        return redirect()->route('admin.workflows.edit', $workflow)->with('success', 'Step added.');
    }

    public function update(Request $request, ApprovalWorkflow $workflow, ApprovalStep $step): RedirectResponse
    {
        $this->authorize('workflow.manage');

        abort_unless($step->workflow_id === $workflow->id, 404);

        $data = $this->validated($request, $workflow, $step);

        $step->update($data);

        return redirect()->route('admin.workflows.edit', $workflow)->with('success', 'Step updated.');
    }

    public function destroy(ApprovalWorkflow $workflow, ApprovalStep $step): RedirectResponse
    {
        $this->authorize('workflow.manage');

        abort_unless($step->workflow_id === $workflow->id, 404);

        $step->delete();

        return redirect()->route('admin.workflows.edit', $workflow)->with('success', 'Step removed.');
    }

    private function validated(Request $request, ApprovalWorkflow $workflow, ?ApprovalStep $step = null): array
    {
        $unique = Rule::unique('approval_steps', 'level')->where('workflow_id', $workflow->id);

        if ($step) {
            $unique->ignore($step->id);
        }

        $data = $request->validate([
            'level'            => ['required', 'integer', 'min:1', 'max:255', $unique],
            'approver_type'    => 'required|in:role,user',
            'approver_role'    => 'required_if:approver_type,role|nullable|string|exists:roles,name',
            'approver_user_id' => 'required_if:approver_type,user|nullable|integer|exists:users,id',
            'escalation_hours' => 'nullable|integer|min:1',
        ]);

        // Keep the unused side of the role/user pair null so approver resolution is unambiguous.
        if ($data['approver_type'] === 'role') {
            $data['approver_user_id'] = null;
        } else {
            $data['approver_role'] = null;
        }

        return $data;
    }
}
