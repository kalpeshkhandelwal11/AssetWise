<?php

namespace App\Http\Controllers\Nhai;

use App\Exports\TakeoverTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TakeoverLinesImport;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PlazaTakeover;
use App\Models\PlazaTakeoverLine;
use App\Models\Room;
use App\Services\TakeoverService;
use App\Support\CompanyVisibility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/** Toll-plaza takeovers (Phase 3) — thin; logic in TakeoverService. */
class TakeoverController extends Controller
{
    public function __construct(private readonly TakeoverService $takeovers)
    {
    }

    public function create(Request $request): View
    {
        $this->authorize('takeover.manage');

        return view('modules.nhai.takeovers.form', $this->formData(new PlazaTakeover([
            'kind'          => $request->input('kind') === 'purchase' ? 'purchase' : 'nhai',
            'takeover_date' => now(),
            'from_company_id' => Company::where('party_type', 'external_authority')->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('takeover.manage');

        $takeover = $this->takeovers->createDraft($this->validated($request), $request->user());

        return redirect()->route('nhai.takeovers.show', $takeover)->with('success', "Takeover {$takeover->reference_no} created — add its assets.");
    }

    public function show(PlazaTakeover $takeover): View
    {
        $this->authorize('nhai.view');
        $this->assertVisible($takeover);

        $takeover->load(['lines.category', 'lines.room', 'location', 'fromCompany', 'company', 'custodian', 'requestedBy', 'approvalRequest']);

        return view('modules.nhai.takeovers.show', [
            'takeover'   => $takeover,
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'types'      => AssetType::where('is_active', true)->orderBy('name')->get(),
            'rooms'      => Room::whereHas('floor.building', fn ($q) => $q->where('location_id', $takeover->location_id))->orderBy('name')->get(),
            'assetCount' => $takeover->assets()->count(),
        ]);
    }

    public function edit(PlazaTakeover $takeover): View
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        return view('modules.nhai.takeovers.form', $this->formData($takeover));
    }

    public function update(Request $request, PlazaTakeover $takeover): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $this->takeovers->updateDraft($takeover, $this->validated($request, $takeover));

        return redirect()->route('nhai.takeovers.show', $takeover)->with('success', 'Takeover updated.');
    }

    public function storeLine(Request $request, PlazaTakeover $takeover): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $data = $request->validate([
            'section'            => 'nullable|string|max:255',
            'item_name'          => 'required|string|max:255',
            'category_id'        => 'required|exists:asset_categories,id',
            'asset_type_id'      => 'nullable|exists:asset_types,id',
            'manufacturer'       => 'nullable|string|max:255',
            'model'              => 'nullable|string|max:255',
            'quantity'           => 'required|integer|min:1|max:'.TakeoverService::MAX_ASSETS,
            'serial_number'      => 'nullable|string|max:255',
            'position_label'     => 'nullable|string|max:255',
            'unit_price'         => [$takeover->isNhai() ? 'prohibited' : 'required', 'nullable', 'numeric', 'min:0'],
            'room_id'            => 'nullable|exists:rooms,id',
            'location_note'      => 'nullable|string|max:255',
            'condition'          => ['required', Rule::in(array_keys(PlazaTakeoverLine::CONDITIONS))],
            'not_functional_qty' => 'nullable|integer|min:0',
            'warranty_years'     => 'nullable|numeric|min:0|max:50',
            'remarks'            => 'nullable|string|max:2000',
        ]);

        if (! empty($data['room_id']) && $room = Room::with('floor')->find($data['room_id'])) {
            $data['floor_id'] = $room->floor_id;
            $data['building_id'] = $room->floor?->building_id;
        }

        // The category's custom attributes, validated exactly as on Add Asset (required fields,
        // types, dropdown options, uniqueness), plus photos — both copied to every unit.
        $request->validate([
            'photos'   => 'nullable|array|max:8',
            'photos.*' => ['image', new \App\Rules\ImageUnderSize(2048)], // compressed client-side first
        ]);

        $fieldService = app(\App\Services\DynamicFieldService::class);
        $resolved = $fieldService->resolveForCategory((int) $data['category_id']);
        try {
            $fieldValues = $fieldService->validate($request->input('fields', []), $resolved);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $k) => ["fields.{$k}" => $m])->all()
            );
        }
        $fieldValues = array_filter($fieldValues, fn ($v) => $v !== null && $v !== '');

        // A "must be unique" attribute (IMEI, MAC…) cannot be shared by several units.
        $uniqueFilled = $resolved->filter(fn ($f) => $f->isUnique && array_key_exists($f->fieldKey, $fieldValues));
        if ((int) $data['quantity'] > 1 && $uniqueFilled->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => $uniqueFilled->pluck('label')->join(', ') . ' must be unique per asset — add these units as separate lines (quantity 1 each).',
            ]);
        }

        $data['field_values'] = $fieldValues ?: null;
        $data['photo_paths'] = collect($request->file('photos', []))
            ->map(fn ($file) => $file->store("takeovers/{$takeover->id}/lines", 'public'))
            ->values()->all() ?: null;

        $this->takeovers->addLine($takeover, $data);

        return back()->with('success', 'Line added.');
    }

    public function destroyLine(PlazaTakeover $takeover, PlazaTakeoverLine $line): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $this->takeovers->removeLine($takeover, $line);

        return back()->with('success', 'Line removed.');
    }

    public function template(PlazaTakeover $takeover): Response
    {
        $this->authorize('takeover.manage');

        return Excel::download(new TakeoverTemplateExport($takeover), "takeover-{$takeover->reference_no}-template.xlsx");
    }

    public function import(Request $request, PlazaTakeover $takeover): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $import = new TakeoverLinesImport($takeover);
        Excel::import($import, $request->file('file'));

        $count = $this->takeovers->addLines($takeover, $import->lines);

        return back()->with('success', "{$count} line(s) imported.");
    }

    public function submit(Request $request, PlazaTakeover $takeover): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $this->takeovers->submit($takeover, $request->user());

        return back()->with('success', "Submitted for approval — {$takeover->totalUnits()} asset(s) are in Draft until it is approved.");
    }

    public function reopen(PlazaTakeover $takeover): RedirectResponse
    {
        $this->authorize('takeover.manage');
        $this->assertVisible($takeover);

        $this->takeovers->reopen($takeover);

        return back()->with('success', 'Takeover reopened as a draft.');
    }

    public function certificate(PlazaTakeover $takeover): Response
    {
        $this->authorize('nhai.view');
        $this->assertVisible($takeover);

        $takeover->load(['lines.category', 'lines.room', 'location', 'fromCompany', 'company', 'custodian']);

        return Pdf::loadView('modules.nhai.pdf.takeover-certificate', [
            'takeover'       => $takeover,
            'showPrices'     => ! $takeover->isNhai() && auth()->user()->can('assets.view_financials'),
        ])->setPaper('a4', 'landscape')->download("takeover-{$takeover->reference_no}.pdf");
    }

    // ---------------------------------------------------------------- helpers

    private function validated(Request $request, ?PlazaTakeover $takeover = null): array
    {
        $visible = CompanyVisibility::ids();

        $data = $request->validate([
            'kind'            => [$takeover ? 'prohibited' : 'required', Rule::in(array_keys(PlazaTakeover::KINDS))],
            'location_id'     => 'required|exists:locations,id',
            'from_company_id' => 'required|exists:companies,id',
            'company_id'      => array_values(array_filter(['required', 'exists:companies,id', $visible === null ? null : Rule::in($visible)])),
            'custodian_id'    => 'nullable|exists:employees,id',
            'takeover_date'   => 'required|date',
            'document_no'     => 'nullable|string|max:255',
            'document'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'signatories'     => 'nullable|array',
            'signatories.*'   => 'nullable|string|max:255',
            'remarks'         => 'nullable|string|max:2000',
        ]);

        // Default custodian at a plaza: its manager.
        if (empty($data['custodian_id'])) {
            $data['custodian_id'] = Location::find($data['location_id'])?->manager_employee_id;
        }

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('nhai/takeovers', 'public');
        }
        unset($data['document']);

        $data['signatories'] = array_filter($data['signatories'] ?? []);

        return $data;
    }

    private function formData(PlazaTakeover $takeover): array
    {
        return [
            'takeover'  => $takeover,
            'locations' => Location::where('is_active', true)->orderByDesc('is_toll_plaza')->orderBy('name')->get(),
            'parties'   => Company::where('is_active', true)->where('party_type', '!=', 'internal')->orderBy('name')->get(),
            'companies' => Company::visible()->internal()->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::visible()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']),
        ];
    }

    private function assertVisible(PlazaTakeover $takeover): void
    {
        abort_unless(CompanyVisibility::canSee($takeover->company_id), 404);
    }
}
