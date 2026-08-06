<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->with(['company', 'category', 'assetType', 'status', 'location', 'custodian']);

        if ($request->boolean('show_deleted') && $request->user()->can('assets.delete')) {
            $query->onlyTrashed();
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('serial_number', 'like', "%$s%"));
        }

        foreach (['company_id', 'category_id', 'asset_type_id', 'status_id', 'location_id', 'custodian_id', 'department_id', 'branch_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        $assets = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('modules.assets.index', [
            'assets'     => $assets,
            'companies'  => Company::orderBy('name')->get(),
            'statuses'   => AssetStatus::orderBy('name')->get(),
            'types'      => AssetType::orderBy('name')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('modules.assets.form', [
            'asset' => new Asset(),
        ] + $this->formLookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $data = $request->validate($this->rules(companyRequired: true));

        $asset = $this->assets->create($data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load(['company', 'category', 'assetType', 'status', 'location', 'building', 'floor', 'room', 'custodian', 'department', 'branch', 'photos', 'attachments', 'creator', 'updater']);

        $activities = Activity::where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->latest()
            ->get();

        return view('modules.assets.show', compact('asset', 'activities'));
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('modules.assets.form', [
            'asset' => $asset,
        ] + $this->formLookups());
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $canChangeCompany = $request->user()->can('companies.manage');

        $data = $request->validate($this->rules(companyRequired: false, companyEditable: $canChangeCompany));

        // Category is locked once custom field data exists (M04 enforces the real check;
        // Asset::hasCustomFieldData() is a stub returning false until then).
        if ($asset->hasCustomFieldData() && ! $request->user()->can('assets.override_category')) {
            unset($data['category_id']);
        }

        $this->assets->update($asset, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $this->assets->delete($asset);

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }

    private function rules(bool $companyRequired, bool $companyEditable = true): array
    {
        $companyRule = match (true) {
            $companyRequired => 'required|exists:companies,id',
            $companyEditable => 'sometimes|exists:companies,id',
            default           => 'prohibited',
        };

        return [
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'serial_number'    => 'nullable|string|max:255',
            'model'            => 'nullable|string|max:255',
            'manufacturer'     => 'nullable|string|max:255',
            'company_id'       => $companyRule,
            'category_id'      => 'required|exists:asset_categories,id',
            'asset_type_id'    => 'required|exists:asset_types,id',
            'status_id'        => 'required|exists:asset_statuses,id',
            'location_id'      => 'nullable|exists:locations,id',
            'building_id'      => 'nullable|exists:buildings,id',
            'floor_id'         => 'nullable|exists:floors,id',
            'room_id'          => 'nullable|exists:rooms,id',
            'custodian_id'     => 'nullable|exists:users,id',
            'department_id'    => 'nullable|exists:departments,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'purchase_date'    => 'nullable|date',
            'purchase_cost'    => 'nullable|numeric|min:0',
            'vendor'           => 'nullable|string|max:255',
            'warranty_expiry'  => 'nullable|date',
            'amc_expiry'       => 'nullable|date',
            'notes'            => 'nullable|string',
        ];
    }

    private function formLookups(): array
    {
        return [
            'companies'   => Company::where('is_active', true)->orderBy('name')->get(),
            'categories'  => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'types'       => AssetType::where('is_active', true)->orderBy('name')->get(),
            'statuses'    => AssetStatus::where('is_active', true)->orderBy('name')->get(),
            'locations'   => Location::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'branches'    => Branch::where('is_active', true)->orderBy('name')->get(),
            'custodians'  => User::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
