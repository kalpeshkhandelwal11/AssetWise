<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(private readonly AuditService $audits)
    {
    }

    /** GET /audits/verify — worklist across the user's assigned active campaigns. */
    public function index(Request $request): View
    {
        $this->authorize('audit.verify');

        $user = $request->user();

        $campaignsQuery = AuditCampaign::query()->where('status', 'active');
        if (! $user->can('audit.manage')) {
            $campaignsQuery->whereHas('auditors', fn ($q) => $q->where('user_id', $user->id));
        }
        $campaigns = $campaignsQuery->orderBy('name')->get();

        $selectedCampaign = $request->filled('campaign')
            ? $campaigns->firstWhere('id', $request->integer('campaign'))
            : $campaigns->first();

        $items = collect();
        if ($selectedCampaign) {
            $query = $selectedCampaign->items()->with(['asset', 'expectedLocation', 'expectedCustodian', 'verifiedBy']);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->whereHas('asset', fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%"));
            }

            $items = $query->orderBy('status')->orderBy('id')->paginate(25)->withQueryString();
        }

        return view('modules.audits.verify.index', [
            'campaigns'        => $campaigns,
            'selectedCampaign' => $selectedCampaign,
            'items'            => $items,
            'focusItemId'      => $request->integer('item') ?: null,
        ]);
    }

    /** POST /audits/items/{item}/verify */
    public function store(Request $request, AuditItem $item): RedirectResponse
    {
        $this->authorize('audit.verify');

        $data = $request->validate([
            'status' => 'required|in:verified,missing,damaged',
            'notes'  => 'nullable|string|max:2000',
            'photo'  => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store("audits/{$item->campaign_id}", 'public');
        }

        $this->audits->verify($item, $data, $request->user());

        return redirect()->route('audits.verify', ['campaign' => $item->campaign_id])
            ->with('success', "Item marked {$data['status']}.");
    }
}
