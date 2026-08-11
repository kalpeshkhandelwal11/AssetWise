<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditCampaign;
use App\Models\AuditType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private readonly AuditService $audits)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('audit.manage');

        $query = AuditCampaign::query()->with(['auditType', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('audit_type_id')) {
            $query->where('audit_type_id', $request->input('audit_type_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return view('modules.audits.campaigns.index', [
            'campaigns'  => $query->latest('id')->paginate(20)->withQueryString(),
            'auditTypes' => AuditType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('audit.manage');

        return view('modules.audits.campaigns.form', [
            'campaign' => new AuditCampaign(),
        ] + $this->lookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('audit.manage');

        $data = $request->validate($this->rules());

        $campaign = $this->audits->create($data, $request->user());

        return redirect()->route('audits.campaigns.show', $campaign)->with('success', 'Audit campaign created.');
    }

    public function show(AuditCampaign $campaign): View
    {
        $this->authorize('audit.manage');

        $campaign->load(['auditType', 'creator', 'closedBy', 'auditors']);

        return view('modules.audits.campaigns.show', [
            'campaign'      => $campaign,
            'progress'      => $this->audits->progressFor($campaign),
            'matchingCount' => $campaign->isDraft() ? $this->audits->scopedAssetQuery($campaign)->count() : null,
            'items'         => $campaign->items()->with(['asset', 'verifiedBy'])->latest('id')->paginate(20),
        ]);
    }

    public function edit(AuditCampaign $campaign): View|RedirectResponse
    {
        $this->authorize('audit.manage');

        if (! $campaign->isDraft()) {
            return redirect()->route('audits.campaigns.show', $campaign)
                ->with('error', 'Only a draft campaign can be edited.');
        }

        return view('modules.audits.campaigns.form', [
            'campaign' => $campaign->load('auditors'),
        ] + $this->lookups());
    }

    public function update(Request $request, AuditCampaign $campaign): RedirectResponse
    {
        $this->authorize('audit.manage');

        $data = $request->validate($this->rules());

        $this->audits->update($campaign, $data);

        return redirect()->route('audits.campaigns.show', $campaign)->with('success', 'Audit campaign updated.');
    }

    public function destroy(AuditCampaign $campaign): RedirectResponse
    {
        $this->authorize('audit.manage');

        $this->audits->delete($campaign);

        return redirect()->route('audits.campaigns.index')->with('success', 'Audit campaign deleted.');
    }

    public function activate(AuditCampaign $campaign): RedirectResponse
    {
        $this->authorize('audit.manage');

        $count = $this->audits->activate($campaign, request()->user());

        return redirect()->route('audits.campaigns.show', $campaign)->with('success', "Campaign activated — {$count} assets in scope.");
    }

    public function close(Request $request, AuditCampaign $campaign): RedirectResponse
    {
        $this->authorize('audit.manage');

        $this->audits->close($campaign, $request->user());

        return redirect()->route('audits.campaigns.show', $campaign)->with('success', 'Campaign closed.');
    }

    private function rules(): array
    {
        return [
            'name'                => 'required|string|max:255',
            'audit_type_id'       => 'required|exists:audit_types,id',
            'description'         => 'nullable|string',
            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'scope'               => 'nullable|array',
            'scope.company_id'    => 'nullable|exists:companies,id',
            'scope.category_id'   => 'nullable|exists:asset_categories,id',
            'scope.asset_type_id' => 'nullable|exists:asset_types,id',
            'scope.status_id'     => 'nullable|exists:asset_statuses,id',
            'scope.location_id'   => 'nullable|exists:locations,id',
            'scope.building_id'   => 'nullable|exists:buildings,id',
            'scope.department_id' => 'nullable|exists:departments,id',
            'scope.branch_id'     => 'nullable|exists:branches,id',
            'auditor_ids'         => 'nullable|array',
            'auditor_ids.*'       => 'exists:users,id',
        ];
    }

    private function lookups(): array
    {
        return [
            'auditTypes' => AuditType::where('is_active', true)->orderBy('name')->get(),
            'companies'  => Company::where('is_active', true)->orderBy('name')->get(),
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'assetTypes' => AssetType::where('is_active', true)->orderBy('name')->get(),
            'statuses'   => AssetStatus::where('is_active', true)->orderBy('name')->get(),
            'locations'  => Location::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'branches'   => Branch::where('is_active', true)->orderBy('name')->get(),
            'auditors'   => User::permission('audit.verify')->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
