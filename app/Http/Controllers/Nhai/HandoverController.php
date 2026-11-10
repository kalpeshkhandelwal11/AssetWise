<?php

namespace App\Http\Controllers\Nhai;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PlazaHandover;
use App\Services\HandoverService;
use App\Support\CompanyVisibility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Toll-plaza handovers at contract end (Phase 3) — thin; logic in HandoverService. */
class HandoverController extends Controller
{
    public function __construct(private readonly HandoverService $handovers)
    {
    }

    public function create(): View
    {
        $this->authorize('handover.manage');

        return view('modules.nhai.handovers.form', [
            'locations' => Location::where('is_active', true)->orderByDesc('is_toll_plaza')->orderBy('name')->get(),
            'companies' => Company::visible()->internal()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('handover.manage');
        $visible = CompanyVisibility::ids();

        $data = $request->validate([
            'location_id'   => 'required|exists:locations,id',
            'company_id'    => array_values(array_filter(['required', 'exists:companies,id', $visible === null ? null : Rule::in($visible)])),
            'handover_date' => 'required|date',
            'document_no'   => 'nullable|string|max:255',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'signatories'   => 'nullable|array',
            'signatories.*' => 'nullable|string|max:255',
            'remarks'       => 'nullable|string|max:2000',
        ]);

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('nhai/handovers', 'public');
        }
        unset($data['document']);
        $data['signatories'] = array_filter($data['signatories'] ?? []);

        $handover = $this->handovers->createDraft($data, $request->user());

        return redirect()->route('nhai.handovers.show', $handover)
            ->with('success', "Handover {$handover->reference_no} drafted with {$handover->lines()->count()} asset(s) — choose what happens to each.");
    }

    public function show(PlazaHandover $handover): View
    {
        $this->authorize('nhai.view');
        $this->assertVisible($handover);

        $handover->load(['lines.asset.company', 'lines.asset.status', 'lines.asset.category', 'lines.buyer', 'lines.toLocation', 'location', 'company', 'authority', 'requestedBy', 'approvalRequest']);

        return view('modules.nhai.handovers.show', [
            'handover'  => $handover,
            'blockers'  => $handover->isDraft() ? $this->handovers->blockers($handover) : [],
            'buyers'    => Company::where('is_active', true)->where('party_type', '!=', 'external_authority')
                ->where('id', '!=', $handover->company_id)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->where('id', '!=', $handover->location_id)->orderBy('name')->get(),
            'employees' => Employee::visible()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']),
        ]);
    }

    public function updateLines(Request $request, PlazaHandover $handover): RedirectResponse
    {
        $this->authorize('handover.manage');
        $this->assertVisible($handover);

        $request->validate([
            'lines'                    => 'required|array',
            'lines.*.action'           => ['required', Rule::in(array_keys(PlazaHandover::ACTIONS))],
            'lines.*.sale_price'       => 'nullable|numeric|min:0',
            'lines.*.buyer_company_id' => 'nullable|exists:companies,id',
            'lines.*.buyer_name'       => 'nullable|string|max:255',
            'lines.*.to_location_id'   => 'nullable|exists:locations,id',
            'lines.*.to_custodian_id'  => 'nullable|exists:employees,id',
            'lines.*.remarks'          => 'nullable|string|max:1000',
        ]);

        $this->handovers->updateLines($handover, $request->input('lines', []));

        return $request->boolean('submit_after')
            ? $this->submit($request, $handover)
            : back()->with('success', 'Handover choices saved.');
    }

    public function refresh(PlazaHandover $handover): RedirectResponse
    {
        $this->authorize('handover.manage');
        $this->assertVisible($handover);
        abort_unless($handover->isDraft(), 422);

        $this->handovers->refreshLines($handover);

        return back()->with('success', 'Asset list refreshed from the plaza.');
    }

    public function submit(Request $request, PlazaHandover $handover): RedirectResponse
    {
        $this->authorize('handover.manage');
        $this->assertVisible($handover);

        $this->handovers->submit($handover, $request->user());

        return redirect()->route('nhai.handovers.show', $handover)->with('success', 'Handover submitted for approval — its assets are locked until then.');
    }

    public function reopen(PlazaHandover $handover): RedirectResponse
    {
        $this->authorize('handover.manage');
        $this->assertVisible($handover);

        $this->handovers->reopen($handover);

        return back()->with('success', 'Handover reopened as a draft.');
    }

    public function retry(Request $request, PlazaHandover $handover): RedirectResponse
    {
        $this->authorize('handover.manage');
        $this->assertVisible($handover);

        $this->handovers->retry($handover, $request->user());

        return back()->with($handover->fresh()->status === 'approved' ? 'success' : 'error',
            $handover->fresh()->status === 'approved' ? 'Handover applied.' : 'Still failing: '.$handover->fresh()->apply_error);
    }

    public function certificate(PlazaHandover $handover): Response
    {
        $this->authorize('nhai.view');
        $this->assertVisible($handover);

        $handover->load(['lines.asset.category', 'lines.buyer', 'lines.toLocation', 'location', 'company', 'authority']);

        return Pdf::loadView('modules.nhai.pdf.handover-certificate', [
            'handover'   => $handover,
            'showPrices' => auth()->user()->can('assets.view_financials'),
        ])->setPaper('a4', 'landscape')->download("handover-{$handover->reference_no}.pdf");
    }

    private function assertVisible(PlazaHandover $handover): void
    {
        abort_unless(CompanyVisibility::canSee($handover->company_id), 404);
    }
}
