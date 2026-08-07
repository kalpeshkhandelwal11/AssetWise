<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private WorkflowService $workflows)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $user = auth()->user();

        return view('approvals.index', [
            'requests'  => $this->workflows->pendingFor($user),
            'submitted' => ApprovalRequest::where('submitted_by', $user->id)
                ->with(['workflow', 'approvable'])
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function show(ApprovalRequest $approval_request): View
    {
        $this->authorize('view', $approval_request);

        $approval_request->load(['workflow.steps.approverUser', 'approvable', 'submittedBy', 'actions.user']);

        return view('approvals.show', [
            'request'   => $approval_request,
            'canAct'    => $this->workflows->canAct(auth()->user(), $approval_request),
            'escalated' => $this->workflows->hasEscalated($approval_request),
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval_request): RedirectResponse
    {
        $this->authorize('act', $approval_request);

        $data = $request->validate(['comment' => 'nullable|string|max:1000']);

        $result = $this->workflows->approve($approval_request, $request->user(), $data['comment'] ?? null);

        return redirect()->route('approvals.index')->with('success', $result->status === 'approved'
            ? 'Request fully approved.'
            : 'Approved — advanced to step ' . $result->current_step . '.');
    }

    public function reject(Request $request, ApprovalRequest $approval_request): RedirectResponse
    {
        $this->authorize('act', $approval_request);

        $data = $request->validate(['comment' => 'required|string|max:1000']);

        $this->workflows->reject($approval_request, $request->user(), $data['comment']);

        return redirect()->route('approvals.index')->with('success', 'Request rejected.');
    }
}
