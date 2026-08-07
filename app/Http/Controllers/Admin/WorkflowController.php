<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class WorkflowController extends Controller
{
    public function __construct(private WorkflowService $workflows)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('workflow.manage');

        $query = ApprovalWorkflow::withCount('steps');

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $workflows = $query->orderBy('module')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.workflows.index', [
            'workflows' => $workflows,
            'modules'   => ApprovalWorkflow::MODULES,
        ]);
    }

    public function create(): View
    {
        $this->authorize('workflow.manage');

        return view('admin.workflows.form', $this->formData(new ApprovalWorkflow(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('workflow.manage');

        $data = $this->validated($request);

        $workflow = ApprovalWorkflow::create($data + ['is_active' => false]);

        if ($request->boolean('is_active')) {
            $this->workflows->activate($workflow);
        }

        return redirect()->route('admin.workflows.edit', $workflow)
            ->with('success', 'Workflow created. Add its approval steps below.');
    }

    public function edit(ApprovalWorkflow $workflow): View
    {
        $this->authorize('workflow.manage');

        $workflow->load('steps.approverUser');

        return view('admin.workflows.form', $this->formData($workflow));
    }

    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('workflow.manage');

        $data = $this->validated($request);

        $workflow->update($data);

        if ($request->boolean('is_active')) {
            // Activating deactivates the module's other workflows — keeps the
            // "exactly one active per module" invariant WorkflowService::submit() relies on.
            $this->workflows->activate($workflow);
        } else {
            $workflow->update(['is_active' => false]);
        }

        return redirect()->route('admin.workflows.index')
            ->with('success', 'Workflow updated.');
    }

    public function destroy(ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('workflow.manage');

        // Never hard-delete — in-flight approval_requests reference this workflow.
        $workflow->update(['is_active' => false]);

        return redirect()->route('admin.workflows.index')
            ->with('success', 'Workflow deactivated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'   => 'required|string|max:255',
            'module' => ['required', Rule::in(ApprovalWorkflow::MODULES)],
        ]);
    }

    private function formData(ApprovalWorkflow $workflow): array
    {
        return [
            'workflow' => $workflow,
            'modules'  => ApprovalWorkflow::MODULES,
            'roles'    => Role::orderBy('name')->pluck('name'),
            'users'    => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }
}
