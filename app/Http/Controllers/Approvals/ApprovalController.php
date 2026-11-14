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

        $approval_request->load(['workflow.steps.approverUser', 'approvable', 'submittedBy', 'actions.user', 'attachments.uploadedBy']);

        // Bulk movement batches: eager-load the child line items so the approver can see each
        // asset in the batch (read-only) without an N+1 per row.
        $approvable = $approval_request->approvable;
        $holdings = collect();

        if ($approvable instanceof \App\Models\AssetMovementBatch) {
            $approvable->load([
                'movements.asset.category', 'movements.movementType', 'movements.toLocation',
                'movements.toCustodian', 'movements.toCompany', 'movements.toDepartment',
            ]);
            $holdings = app(\App\Services\MovementService::class)->existingHoldingsFor($approvable);
        } elseif ($approvable instanceof \App\Models\AssetMovement) {
            // A single movement used to show nothing but its label — give the approver the facts.
            $approvable->load([
                'asset.category', 'asset.status', 'movementType',
                'fromCompany', 'toCompany', 'fromLocation', 'toLocation',
                'fromCustodian', 'toCustodian', 'fromDepartment', 'toDepartment', 'toStatus',
            ]);
            $holdings = app(\App\Services\MovementService::class)->existingHoldingsFor($approvable);
        } elseif ($approvable instanceof \App\Models\KitAssignment) {
            // Kit assigned under one approval ("single" mode): same view of what the
            // destination already holds, across every category in the kit.
            $holdings = app(\App\Services\MovementService::class)->existingHoldingsFor($approvable);
        } elseif ($approvable instanceof \App\Models\ImportBatch) {
            $approvable->load('category', 'user');
        }

        return view('approvals.show', [
            'holdings'  => $holdings,
            'importAssets' => $approvable instanceof \App\Models\ImportBatch
                ? \App\Models\Asset::whereIn('id', $approvable->rows()->where('status', 'success')->pluck('asset_id'))
                    ->with('status')->orderBy('name')->paginate(25)
                : null,
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
