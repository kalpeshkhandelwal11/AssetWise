<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\DisposalRequest;
use App\Models\Tag;
use App\Models\User;
use App\Services\Reports\Concerns\FiltersByCompany;
use Illuminate\Support\Collection;

/**
 * Backs the /dashboard KPI widgets. Every query is company-scoped via FiltersByCompany
 * except two deliberate exceptions, documented where they occur below: Available Tags
 * (tags have no company_id until assigned to an asset) and the polymorphic
 * ApprovalRequest widgets (resolved by duck-typing the approvable's asset instead).
 */
class DashboardService
{
    use FiltersByCompany;

    public function __construct(private readonly WorkflowService $workflow)
    {
    }

    public function summary(User $user, ?int $companyId): array
    {
        $totalAssets    = $this->scopeByCompany(Asset::query(), $companyId)->count();
        $assignedAssets = $this->scopeByCompany(
            Asset::whereHas('status', fn ($q) => $q->where('code', 'ASSIGNED')), $companyId
        )->count();
        $inMaintenance = $this->scopeByCompany(
            Asset::whereHas('status', fn ($q) => $q->where('code', 'MAINTENANCE')), $companyId
        )->count();

        // Eager-loading 'approvable.asset' would break when the approvable is itself an Asset
        // (the model-agnostic stand-in WorkflowService's own tests use) — it has no `asset`
        // relation, so Laravel throws RelationNotFoundException on the nested eager load.
        // filterByApprovableCompany() lazy-accesses ->asset instead, same as pendingFor().
        $pendingRequests = ApprovalRequest::where('status', 'pending')->with('approvable')->get();
        $pendingRequests = $this->filterByApprovableCompany($pendingRequests, $companyId);
        $pendingCount = $pendingRequests->count();

        // Available Tags stays global regardless of the company filter — tags in the pool
        // have no company_id until assigned to an asset.
        $availableTags = Tag::where('status', 'available')->count();

        $disposedYear = $this->scopeByRelatedAssetCompany(
            DisposalRequest::where('status', 'scrapped')->whereYear('updated_at', now()->year), $companyId
        )->count();

        $warrantyExpiring = $this->scopeByCompany(
            Asset::whereBetween('warranty_expiry', [now(), now()->addDays(30)]), $companyId
        )->count();
        $amcExpiring = $this->scopeByCompany(
            Asset::whereBetween('amc_expiry', [now(), now()->addDays(30)]), $companyId
        )->count();

        $recentMovements = $this->scopeByMovementCompany(
            AssetMovement::with(['asset', 'movementType', 'requestedBy']), $companyId
        )->latest()->limit(8)->get();

        $pendingApprovals = $this->filterByApprovableCompany($this->workflow->pendingFor($user), $companyId)
            ->take(5);

        $expiryAlerts = $this->scopeByCompany(
            Asset::where(function ($q) {
                $q->whereBetween('warranty_expiry', [now(), now()->addDays(30)])
                  ->orWhereBetween('amc_expiry', [now(), now()->addDays(30)]);
            })->with('status'), $companyId
        )->limit(5)->get();

        return compact(
            'totalAssets', 'assignedAssets', 'inMaintenance', 'pendingCount',
            'availableTags', 'disposedYear', 'warrantyExpiring', 'amcExpiring',
            'recentMovements', 'pendingApprovals', 'expiryAlerts',
        );
    }

    /**
     * ApprovalRequest is polymorphic with no company_id of its own, so company scoping is
     * resolved by duck-typing the loaded approvable (AssetMovement/DisposalRequest/etc. all
     * expose an `asset` relation) rather than a query-level join.
     *
     * @param  Collection<int, ApprovalRequest>  $requests
     * @return Collection<int, ApprovalRequest>
     */
    private function filterByApprovableCompany(Collection $requests, ?int $companyId): Collection
    {
        if ($companyId === null) {
            return $requests;
        }

        return $requests->filter(function (ApprovalRequest $request) use ($companyId) {
            $approvable = $request->approvable;

            return ($approvable?->asset?->company_id ?? $approvable?->company_id) === $companyId;
        })->values();
    }
}
