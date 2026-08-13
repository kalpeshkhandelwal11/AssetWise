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
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tag;
use App\Models\User;
use App\Models\ApprovalWorkflow;
use App\Services\AssetNamingService;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use App\Services\TagService;
use App\Services\WorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly DynamicFieldService $fields,
        private readonly TagService $tags,
        private readonly AssetNamingService $naming,
        private readonly WorkflowService $workflows,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->with(['company', 'category', 'assetType', 'status', 'location', 'building', 'room', 'custodian']);

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

        // Sort: whitelist -> [column, direction]. Default "Last updated".
        $sortMap = [
            'updated'  => ['updated_at', 'desc'],
            'name'     => ['name', 'asc'],
            'asset_id' => ['asset_tag', 'asc'],
            'created'  => ['created_at', 'desc'],
        ];
        $sort = $request->input('sort', 'updated');
        [$sortColumn, $sortDirection] = $sortMap[$sort] ?? $sortMap['updated'];

        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $assets = $query->orderBy($sortColumn, $sortDirection)->paginate($perPage)->withQueryString();

        return view('modules.assets.index', [
            'assets'     => $assets,
            'companies'  => Company::orderBy('name')->get(),
            'statuses'   => AssetStatus::orderBy('name')->get(),
            'types'      => AssetType::orderBy('name')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
            'sort'       => array_key_exists($sort, $sortMap) ? $sort : 'updated',
            'perPage'    => $perPage,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Asset::class);

        $categoryId = $request->old('category_id');

        return view('modules.assets.form', [
            'asset'           => new Asset(),
            'resolvedFields'  => $categoryId ? $this->fields->resolveForCategory((int) $categoryId) : collect(),
            'fieldValues'     => collect(),
            'canChangeCategory' => true,
        ] + $this->formLookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $data = $this->validateAsset($request, companyRequired: true, companyEditable: true);
        $resolved = $data['_resolved_fields'];
        $fieldInput = $data['fields'];
        unset($data['fields'], $data['_resolved_fields']);

        // Media (labelled attachments) + optional barcode tag are validated separately so they
        // never leak into the asset column payload passed to AssetService::create().
        $extras = $request->validate([
            'media'          => 'nullable|array',
            'media.*'        => 'file|max:20480', // 20 MB, mirrors AttachmentController
            'media_labels'   => 'nullable|array',
            'media_labels.*' => 'in:invoice,warranty_card,manual,agreement,photo',
            'tag_id'         => 'nullable|exists:tags,id',
            'tag_number'     => 'nullable|string', // scan-to-assign (camera or hardware scanner)
        ]);

        // Create + (optionally) submit for creation approval atomically, so a WorkflowService
        // misconfiguration never leaves a live asset with no request (CLAUDE.md orphan guard).
        $asset = DB::transaction(function () use ($data, $fieldInput, $resolved, $request) {
            $asset = $this->assets->create($data, $request->user());
            $this->fields->saveValues($asset, $fieldInput, $resolved);

            if ($this->hasActiveCreationWorkflow()) {
                if ($draft = AssetStatus::where('code', 'DRAFT')->first()) {
                    $asset->update(['status_id' => $draft->id]);
                }
                $this->workflows->submit($asset, 'asset_creation', $request->user());
            }

            return $asset;
        });

        $this->storeMedia($asset, $request);

        // Assign a pool tag if one was picked (tag_id) or scanned/typed (tag_number). Mirrors
        // TagController::store — tag_id wins if both are somehow present.
        if ($request->user()->can('tags.assign') && (! empty($extras['tag_id']) || ! empty($extras['tag_number']))) {
            $tag = ! empty($extras['tag_id'])
                ? Tag::find($extras['tag_id'])
                : Tag::where('tag_number', $extras['tag_number'])->first();

            if (! $tag) {
                throw ValidationException::withMessages(['tag_number' => 'No tag found with that number.']);
            }

            $this->tags->assignToAsset($tag, $asset, $request->user());
        }

        $message = $asset->fresh()->isDraft()
            ? 'Asset saved as draft and submitted for approval.'
            : 'Asset created.';

        return redirect()->route('assets.show', $asset)->with('success', $message);
    }

    /** Resubmit a draft (e.g. after a rejection) for creation approval. */
    public function submitForApproval(Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        if (! $asset->isDraft() || $asset->hasPendingCreationApproval()) {
            return back()->with('error', 'This asset is not a draft awaiting submission.');
        }

        $this->workflows->submit($asset, 'asset_creation', request()->user());

        return back()->with('success', 'Asset submitted for approval.');
    }

    private function hasActiveCreationWorkflow(): bool
    {
        return ApprovalWorkflow::where('module', 'asset_creation')->where('is_active', true)->exists();
    }

    /** Bulk: submit every selected DRAFT asset (with no pending request) for creation approval. */
    public function bulkSubmitForApproval(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('assets.edit'), 403);

        $data = $request->validate([
            'asset_ids'   => 'required|array|min:1',
            'asset_ids.*' => 'exists:assets,id',
        ]);

        if (! $this->hasActiveCreationWorkflow()) {
            return back()->with('error', 'No active asset-creation workflow is configured.');
        }

        $submitted = 0;
        foreach (Asset::whereIn('id', $data['asset_ids'])->get() as $asset) {
            if ($asset->isDraft() && ! $asset->hasPendingCreationApproval()) {
                $this->workflows->submit($asset, 'asset_creation', $request->user());
                $submitted++;
            }
        }

        return back()->with(
            $submitted > 0 ? 'success' : 'error',
            $submitted > 0 ? "{$submitted} draft asset(s) submitted for approval." : 'No eligible draft assets in the selection.',
        );
    }

    /** Bulk: a print-friendly PDF summary of the selected assets. */
    public function printList(Request $request)
    {
        $this->authorize('viewAny', Asset::class);

        $data = $request->validate([
            'asset_ids'   => 'required|array|min:1',
            'asset_ids.*' => 'exists:assets,id',
        ]);

        $assets = Asset::with(['company', 'status', 'location', 'building', 'room', 'custodian'])
            ->whereIn('id', $data['asset_ids'])
            ->orderBy('name')
            ->get();

        return Pdf::loadView('modules.assets.print-list', ['assets' => $assets])
            ->download('assets-' . now()->format('Ymd-His') . '.pdf');
    }

    /** Save each uploaded create-form media file as a labelled AssetAttachment (reuses AttachmentController's storage layout). */
    private function storeMedia(Asset $asset, Request $request): void
    {
        $labels = $request->input('media_labels', []);

        foreach ($request->file('media', []) as $i => $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store("assets/{$asset->id}/attachments", 'public');
            $asset->attachments()->create([
                'type'          => $labels[$i] ?? 'photo',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);
        }
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load([
            'company', 'category', 'assetType', 'status', 'location', 'building', 'room', 'floor',
            'custodian', 'department', 'branch', 'photos', 'attachments', 'creator', 'updater',
            'fieldValues.categoryField.options',
            'tagAssignments.tag', 'tagAssignments.assignedBy',
            'movements.movementType', 'movements.toCompany', 'movements.toLocation',
            'movements.toCustodian', 'movements.toDepartment', 'movements.requestedBy',
            'maintenanceRecords.maintenanceType', 'maintenanceRecords.loggedBy',
            'amcContracts.createdBy', 'warrantyRecords.createdBy',
            'kitAssets.kitItem.kit',
        ]);

        $activities = Activity::where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->latest()
            ->get();

        $availableTags = auth()->user()->can('tags.assign') && ! $asset->activeTag()
            ? Tag::where('status', 'available')->orderBy('tag_number')->get()
            : collect();

        return view('modules.assets.show', compact('asset', 'activities', 'availableTags'));
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        $categoryId = (int) old('category_id', $asset->category_id);
        $canChangeCategory = ! $asset->hasCustomFieldData() || auth()->user()->can('assets.override_category');

        $fieldValues = $asset->fieldValues()->with('categoryField')->get()
            ->filter(fn ($fv) => $fv->categoryField !== null)
            ->keyBy(fn ($fv) => $fv->categoryField->field_key)
            ->map(fn ($fv) => $fv->rawValue());

        return view('modules.assets.form', [
            'asset'             => $asset,
            'resolvedFields'    => $this->fields->resolveForCategory($categoryId),
            'fieldValues'       => $fieldValues,
            'canChangeCategory' => $canChangeCategory,
        ] + $this->formLookups());
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $canChangeCompany = $request->user()->can('companies.manage');

        $data = $this->validateAsset($request, companyRequired: false, companyEditable: $canChangeCompany, asset: $asset);
        $resolved = $data['_resolved_fields'];
        $fieldInput = $data['fields'];
        unset($data['fields'], $data['_resolved_fields']);

        // Checkboxes omit themselves from the request when unchecked, so 'sometimes|boolean'
        // alone can never turn is_eol back off — read it explicitly, same as WorkflowController.
        $data['is_eol'] = $request->boolean('is_eol');

        $this->assets->update($asset, $data, $request->user());
        $this->fields->saveValues($asset, $fieldInput, $resolved);

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $this->assets->delete($asset);

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }

    /**
     * Runs the core-field validator and the dynamic-field validator together and merges
     * their error bags into a single ValidationException, so a user sees both a core-field
     * error and a dynamic-field error in one round trip instead of discovering them one at a time.
     */
    private function validateAsset(Request $request, bool $companyRequired, bool $companyEditable, ?Asset $asset = null): array
    {
        $locked = $asset?->exists && $asset->hasCustomFieldData() && ! $request->user()->can('assets.override_category');
        $submittedCategoryId = $request->input('category_id');
        $effectiveCategoryId = $locked ? $asset->category_id : ($submittedCategoryId ? (int) $submittedCategoryId : null);
        $resolved = $effectiveCategoryId ? $this->fields->resolveForCategory($effectiveCategoryId) : collect();

        $errors = [];
        $core = [];
        $dynamic = [];

        try {
            $core = validator($request->all(), $this->rules($companyRequired, $companyEditable, categoryLocked: $locked))->validate();
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $dynamic = $this->fields->validate($request->input('fields', []), $resolved);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $errors["fields.$key"] = $messages;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($locked) {
            unset($core['category_id']);
        }

        return $core + ['fields' => $dynamic, '_resolved_fields' => $resolved];
    }

    private function rules(bool $companyRequired, bool $companyEditable = true, bool $categoryLocked = false): array
    {
        $companyRule = match (true) {
            $companyRequired => 'required|exists:companies,id',
            $companyEditable => 'sometimes|exists:companies,id',
            default           => 'prohibited',
        };

        return [
            'name'             => 'required|string|max:255',
            'asset_tag'        => 'nullable|string|max:255|unique:assets,asset_tag',
            'description'      => 'nullable|string',
            'serial_number'    => 'nullable|string|max:255',
            'model'            => 'nullable|string|max:255',
            'manufacturer'     => 'nullable|string|max:255',
            'company_id'       => $companyRule,
            'category_id'      => $categoryLocked ? 'sometimes|exists:asset_categories,id' : 'required|exists:asset_categories,id',
            'asset_type_id'    => 'required|exists:asset_types,id',
            'status_id'        => 'required|exists:asset_statuses,id',
            'location_id'      => 'nullable|exists:locations,id',
            'building_id'      => 'nullable|exists:buildings,id',
            'floor_id'         => 'nullable|exists:floors,id',
            'room_id'          => 'nullable|exists:rooms,id',
            'custodian_id'     => 'nullable|exists:employees,id',
            'department_id'    => 'nullable|exists:departments,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'purchase_date'    => 'nullable|date',
            'purchase_cost'    => 'nullable|numeric|min:0',
            'vendor'           => 'nullable|string|max:255',
            'vendor_invoice_no' => 'nullable|string|max:255',
            'warranty_expiry'  => 'nullable|date',
            'amc_expiry'       => 'nullable|date',
            'is_eol'           => 'sometimes|boolean',
            'eol_projected_date' => 'nullable|date',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
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
            'custodians'  => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id', 'branch_id']),
            'availableTags' => Tag::where('status', 'available')->orderBy('tag_number')->get(),
            'assetNamingEnabled' => $this->naming->enabled(),
            'assetNamingPreview' => $this->naming->enabled() ? $this->naming->preview() : null,
            // Per-category next-code previews so the form hint updates as the category changes.
            'assetNamingPreviews' => $this->naming->enabled()
                ? AssetCategory::where('is_active', true)->whereNotNull('asset_prefix')->where('asset_prefix', '!=', '')
                    ->get()->mapWithKeys(fn ($c) => [$c->id => $this->naming->preview($c)])
                : collect(),
        ];
    }
}
