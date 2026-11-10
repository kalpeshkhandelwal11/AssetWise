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
use App\Rules\ImageUnderSize;
use App\Services\AssetService;
use App\Services\DepreciationService;
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
        private readonly \App\Services\DepreciationService $depreciation,
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
                ->orWhere('serial_number', 'like', "%$s%")
                // Custom (EAV) fields flagged searchable participate in the same search box.
                ->orWhereHas('fieldValues', fn ($fv) => $fv
                    ->whereHas('categoryField', fn ($cf) => $cf->where('is_searchable', true))
                    ->where(fn ($v) => $v
                        ->where('value_text', 'like', "%$s%")
                        ->orWhere('value_number', 'like', "%$s%"))));
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
            'companies'  => Company::visible()->orderBy('name')->get(),
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
            // Images capped at 2 MB (the client compresses them first); PDFs may be larger.
            'media.*'        => ['file', 'max:20480', new ImageUnderSize(2048)],
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

        // Auto-create depreciation from the category default for live (non-draft) assets — no
        // approval, since it applies already-approved category policy. Drafts get theirs on
        // promotion (ApplyAssetCreation). No-op when there's no category default or no cost.
        if (! $asset->fresh()->isDraft()) {
            $this->depreciation->applyCategoryDefault($asset->fresh());
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
            'movementLocks'     => $this->movementLocks($asset),
            'purchaseMode'      => $this->depreciation->purchaseEditMode($asset),
        ] + $this->formLookups($asset));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $this->assertMovementFieldsUnchanged($request, $asset);

        // assertMovementFieldsUnchanged() has already refused a changed company on a live asset, so
        // what reaches validation is either the unchanged value or a draft's legitimate edit.
        $data = $this->validateAsset($request, companyRequired: false, companyEditable: true, asset: $asset);
        $resolved = $data['_resolved_fields'];
        $fieldInput = $data['fields'];
        unset($data['fields'], $data['_resolved_fields']);

        // Checkboxes omit themselves from the request when unchecked, so 'sometimes|boolean'
        // alone can never turn is_eol back off — read it explicitly, same as WorkflowController.
        $data['is_eol'] = $request->boolean('is_eol');

        // Purchase cost / date feed the depreciation schedule, so they may not drift from it.
        $purchaseMode = $this->depreciation->purchaseEditMode($asset);
        [$newCost, $newDate, $changed] = $this->purchaseChange($asset, $data);
        $holdForApproval = $changed && $this->assertPurchaseEditable($purchaseMode, $newCost, $newDate);

        $correction = DB::transaction(function () use ($asset, $data, $fieldInput, $resolved, $request, $holdForApproval, $newCost, $newDate) {
            $correction = null;
            if ($holdForApproval) {
                // Held back: the asset keeps its current figures until the correction is approved.
                $correction = $this->depreciation->submitPurchaseCorrection($asset, (float) $newCost, $newDate, $request->user());
                unset($data['purchase_cost'], $data['purchase_date']);
            }

            $this->assets->update($asset, $data, $request->user());
            $this->fields->saveValues($asset, $fieldInput, $resolved);

            return $correction;
        });

        // A cost added to an asset with no depreciation yet starts the category default, exactly
        // as on creation (no approval — it applies already-approved category policy). Drafts get
        // theirs on promotion (ApplyAssetCreation).
        if ($purchaseMode === DepreciationService::PURCHASE_FREE && ! $asset->fresh()->isDraft()) {
            $this->depreciation->applyCategoryDefault($asset->fresh());
        }

        $message = $correction
            ? 'Asset updated. The purchase cost/date change was sent for depreciation approval and applies once approved.'
            : 'Asset updated.';

        return redirect()->route('assets.show', $asset)->with('success', $message);
    }

    /**
     * Fields a movement changes on a live asset (MovementService applies exactly these), and the
     * movement that does it. Editing them by hand would skip the approval, the movement history,
     * In Transit / receipt and — for company — the depreciation reset of an inter-company transfer.
     */
    private const MOVEMENT_FIELDS = [
        'company_id'    => 'an Inter-Company Transfer',
        'custodian_id'  => 'an Assignment or Custodian Change',
        'location_id'   => 'a Transfer',
        'department_id' => 'a Transfer',
    ];

    /**
     * Which movement-owned fields the asset form must not change. Drafts are still being set up
     * (and can't be moved yet), so they stay editable; a location or department that was never
     * set can be filled in, since nothing physically moves.
     *
     * @return array<string, bool>
     */
    private function movementLocks(Asset $asset): array
    {
        $live = $asset->exists && ! $asset->isDraft();

        // In transit the destination is decided by the movement — nothing is filled in by hand.
        if ($live && $asset->isInTransit()) {
            return array_fill_keys(array_keys(self::MOVEMENT_FIELDS), true);
        }

        return [
            'company_id'    => $live,
            'custodian_id'  => $live,
            'location_id'   => $live && $asset->location_id !== null,
            'department_id' => $live && $asset->department_id !== null,
        ];
    }

    private function assertMovementFieldsUnchanged(Request $request, Asset $asset): void
    {
        $errors = [];
        foreach ($this->movementLocks($asset) as $field => $locked) {
            if ($locked && $request->has($field) && (string) $request->input($field) !== (string) $asset->{$field}) {
                $errors[$field] = $asset->isInTransit()
                    ? 'This asset is in transit — its custodian, location and company change only when it is received.'
                    : 'This changes through ' . self::MOVEMENT_FIELDS[$field] . ' (Move this asset), so it is approved and recorded in the movement history.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array{0: float|null, 1: string|null, 2: bool} new cost, new date, whether either changed */
    private function purchaseChange(Asset $asset, array $data): array
    {
        $money = fn ($v) => $v === null || $v === '' ? null : round((float) $v, 2);
        $day = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->toDateString() : null;

        $newCost = array_key_exists('purchase_cost', $data) ? $money($data['purchase_cost']) : $money($asset->purchase_cost);
        $newDate = array_key_exists('purchase_date', $data) ? $day($data['purchase_date']) : $day($asset->purchase_date);

        $changed = $newCost !== $money($asset->purchase_cost) || $newDate !== $day($asset->purchase_date);

        return [$newCost, $newDate, $changed];
    }

    /** Throws when a changed purchase cost/date may not be saved; true when it must go for approval. */
    private function assertPurchaseEditable(string $mode, ?float $newCost, ?string $newDate): bool
    {
        $refuse = fn (string $message) => throw ValidationException::withMessages(['purchase_cost' => $message]);

        return match ($mode) {
            DepreciationService::PURCHASE_FREE => false,
            DepreciationService::PURCHASE_LOCKED_ENDED => $refuse(
                'Depreciation for this asset has ended (written off or sold), so its purchase cost and date are history and cannot change.'),
            DepreciationService::PURCHASE_LOCKED_BASIS => $refuse(
                'Depreciation now runs on a different basis (after a transfer or a capitalized repair) — change cost or start date on the Depreciation page.'),
            default => ($newCost === null || $newCost <= 0 || $newDate === null)
                ? $refuse('Purchase cost and date are required while this asset is being depreciated.')
                : true,
        };
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $this->assets->delete($asset);

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }

    /** Bulk: soft-delete every selected asset (restorable from the deleted view). */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('assets.delete'), 403);

        $data = $request->validate([
            'asset_ids'   => 'required|array|min:1',
            'asset_ids.*' => 'exists:assets,id',
        ]);

        $count = 0;
        foreach (Asset::whereIn('id', $data['asset_ids'])->get() as $asset) {
            $this->assets->delete($asset);
            $count++;
        }

        return redirect()->route('assets.index')->with('success', "{$count} asset(s) deleted.");
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
            $core = validator($request->all(), $this->rules($companyRequired, $companyEditable, categoryLocked: $locked, hasPurchaseDate: $request->filled('purchase_date'), asset: $asset))->validate();
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $dynamic = $this->fields->validate($request->input('fields', []), $resolved, $asset?->id);
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

    private function rules(bool $companyRequired, bool $companyEditable = true, bool $categoryLocked = false, bool $hasPurchaseDate = false, ?Asset $asset = null): array
    {
        // Company isolation: a restricted user may only file assets under their own companies.
        $visibleCompanies = \App\Support\CompanyVisibility::ids();
        $companyIn = $visibleCompanies === null ? [] : [\Illuminate\Validation\Rule::in($visibleCompanies)];

        $companyRule = match (true) {
            $companyRequired => ['required', 'exists:companies,id', ...$companyIn],
            $companyEditable => ['sometimes', 'exists:companies,id', ...$companyIn],
            default           => 'prohibited',
        };

        $afterPurchase = $hasPurchaseDate ? '|after_or_equal:purchase_date' : '';

        return [
            'name'             => 'required|string|max:255',
            // ->ignore(): saving an asset with its own tag must not fail as a "duplicate".
            'asset_tag'        => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'description'      => 'nullable|string',
            'serial_number'    => ['nullable', 'string', 'max:255', new \App\Rules\UniqueSerialNumber($asset?->id)],
            'model'            => 'nullable|string|max:255',
            'manufacturer'     => 'nullable|string|max:255',
            'company_id'       => $companyRule,
            'category_id'      => $categoryLocked ? 'sometimes|exists:asset_categories,id' : 'required|exists:asset_categories,id',
            'asset_type_id'    => 'required|exists:asset_types,id',
            // Workflow-only statuses (Draft, In Transit, Scrapped, Sold…) can't be picked by hand;
            // an edit may keep the asset's current one.
            'status_id'        => ['required', 'exists:asset_statuses,id',
                \Illuminate\Validation\Rule::notIn(array_values(array_diff(AssetStatus::workflowOnlyIds(), [(int) $asset?->status_id])))],
            'location_id'      => 'nullable|exists:locations,id',
            'building_id'      => 'nullable|exists:buildings,id',
            'floor_id'         => 'nullable|exists:floors,id',
            'room_id'          => 'nullable|exists:rooms,id',
            'custodian_id'     => 'nullable|exists:employees,id',
            'department_id'    => 'nullable|exists:departments,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'purchase_date'    => 'nullable|date|before_or_equal:today',
            'purchase_cost'    => 'nullable|numeric|min:0',
            'vendor'           => 'nullable|string|max:255',
            'vendor_invoice_no' => 'nullable|string|max:255',
            // Coverage/EOL dates can't predate the purchase (only enforced when a purchase date
            // is given — the comparison field must exist for after_or_equal to be meaningful).
            'warranty_expiry'  => 'nullable|date' . $afterPurchase,
            'amc_expiry'       => 'nullable|date' . $afterPurchase,
            'is_eol'           => 'sometimes|boolean',
            'eol_projected_date' => 'nullable|date' . $afterPurchase,
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'notes'            => 'nullable|string',
        ];
    }

    private function formLookups(?Asset $asset = null): array
    {
        return [
            'companies'   => Company::visible()->where('is_active', true)->orderBy('name')->get(),
            'categories'  => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'types'       => AssetType::where('is_active', true)->orderBy('name')->get(),
            'statuses'    => AssetStatus::where('is_active', true)->manuallySelectable($asset?->status_id)->orderBy('name')->get(),
            'locations'   => Location::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'branches'    => Branch::where('is_active', true)->orderBy('name')->get(),
            'custodians'  => Employee::visible()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id', 'branch_id']),
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
