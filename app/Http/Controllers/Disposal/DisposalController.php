<?php

namespace App\Http\Controllers\Disposal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\DisposalRequest;
use App\Models\DisposalType;
use App\Services\DisposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisposalController extends Controller
{
    public function __construct(private DisposalService $disposals)
    {
    }

    public function index(Request $request): View
    {
        if (! $request->user()->canAny(['disposal.request', 'disposal.approve', 'disposal.complete'])) {
            abort(403);
        }

        $query = DisposalRequest::query()->with(['asset', 'disposalType', 'requestedBy']);

        // Broad visibility requires disposal.approve/complete; a plain requester only sees their own.
        if (! $request->user()->canAny(['disposal.approve', 'disposal.complete'])) {
            $query->where('requested_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('disposal_type_id')) {
            $query->where('disposal_type_id', $request->input('disposal_type_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%"));
        }

        return view('modules.disposals.index', [
            'disposals'     => $query->latest('id')->paginate(20)->withQueryString(),
            'disposalTypes' => DisposalType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('disposal.request');

        $preselected = $request->filled('asset_id') ? Asset::find($request->integer('asset_id')) : null;

        return view('modules.disposals.create', [
            'asset'         => $preselected,
            'assets'        => Asset::where('is_active', true)->orderBy('name')->get(),
            'disposalTypes' => DisposalType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('disposal.request');

        $data = $request->validate([
            'asset_id'         => 'required|exists:assets,id',
            'disposal_type_id' => 'required|exists:disposal_types,id',
            'reason'           => 'required|string|max:1000',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        $disposalRequest = $this->disposals->submit($asset, $data, $request->user());

        return redirect()->route('disposals.show', $disposalRequest)->with('success', 'Disposal request submitted for approval.');
    }

    public function show(Request $request, DisposalRequest $disposal): View
    {
        if ((int) $disposal->requested_by !== (int) $request->user()->id
            && ! $request->user()->canAny(['disposal.approve', 'disposal.complete'])) {
            abort(403);
        }

        $disposal->load(['asset', 'disposalType', 'requestedBy', 'writtenOffBy', 'scrappedBy', 'approvalRequest']);

        return view('modules.disposals.show', ['disposal' => $disposal]);
    }

    public function writeOff(Request $request, DisposalRequest $disposal): RedirectResponse
    {
        $this->authorize('disposal.complete');

        $data = $request->validate(['disposal_value' => 'nullable|numeric|min:0']);

        $this->disposals->writeOff($disposal, $data, $request->user());

        return redirect()->route('disposals.show', $disposal)->with('success', 'Disposal written off.');
    }

    public function scrap(Request $request, DisposalRequest $disposal): RedirectResponse
    {
        $this->authorize('disposal.complete');

        $this->disposals->scrap($disposal, $request->user());

        return redirect()->route('disposals.show', $disposal)->with('success', 'Disposal marked scrapped — asset is now Disposed.');
    }
}
