<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\DisposalRequest;
use App\Models\Tag;
use App\Services\WorkflowService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function __invoke(): View
    {
        $user = auth()->user();

        $totalAssets     = Asset::count();
        $assignedAssets  = Asset::whereHas('status', fn ($q) => $q->where('code', 'ASSIGNED'))->count();
        $inMaintenance   = Asset::whereHas('status', fn ($q) => $q->where('code', 'MAINTENANCE'))->count();
        $pendingCount    = ApprovalRequest::where('status', 'pending')->count();
        $availableTags   = Tag::where('status', 'available')->count();
        $disposedYear    = DisposalRequest::where('status', 'scrapped')
            ->whereYear('updated_at', now()->year)
            ->count();
        $warrantyExpiring = Asset::whereBetween('warranty_expiry', [now(), now()->addDays(30)])->count();
        $amcExpiring      = Asset::whereBetween('amc_expiry', [now(), now()->addDays(30)])->count();

        $recentMovements = AssetMovement::with(['asset', 'movementType', 'requestedBy'])
            ->latest()
            ->limit(8)
            ->get();

        $pendingApprovals = $this->workflow->pendingFor($user)->take(5);

        $expiryAlerts = Asset::where(function ($q) {
            $q->whereBetween('warranty_expiry', [now(), now()->addDays(30)])
              ->orWhereBetween('amc_expiry', [now(), now()->addDays(30)]);
        })->with('status')->limit(5)->get();

        return view('dashboard', compact(
            'totalAssets', 'assignedAssets', 'inMaintenance', 'pendingCount',
            'availableTags', 'disposedYear', 'warrantyExpiring', 'amcExpiring',
            'recentMovements', 'pendingApprovals', 'expiryAlerts',
        ));
    }
}
