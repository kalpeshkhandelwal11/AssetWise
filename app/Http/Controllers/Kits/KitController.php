<?php

namespace App\Http\Controllers\Kits;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Kit;
use App\Models\KitAsset;
use App\Models\KitItem;
use App\Services\KitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KitController extends Controller
{
    public function __construct(private readonly KitService $kits)
    {
    }

    public function index(): View
    {
        $this->authorize('kits.view');

        $kits = Kit::withCount('items')->with('items.kitAssets')->orderBy('name')->paginate(20);

        return view('modules.kits.index', [
            'kits'         => $kits,
            'readiness'    => $kits->mapWithKeys(fn (Kit $kit) => [$kit->id => $this->kits->isReady($kit)]),
        ]);
    }

    public function create(): View
    {
        $this->authorize('kits.manage');

        return view('modules.kits.form', ['kit' => new Kit()] + $this->lookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kits.manage');

        $data = $request->validate($this->rules());
        $kit = $this->kits->create($data, $request->user());

        return redirect()->route('kits.edit', $kit)->with('success', 'Kit template created — add slots below.');
    }

    public function edit(Kit $kit): View
    {
        $this->authorize('kits.manage');

        $kit->load('items.category', 'items.kitAssets.asset');

        return view('modules.kits.form', [
            'kit'      => $kit,
            'isReady'  => $this->kits->isReady($kit),
        ] + $this->lookups());
    }

    public function update(Request $request, Kit $kit): RedirectResponse
    {
        $this->authorize('kits.manage');

        $data = $request->validate($this->rules($kit));
        $this->kits->update($kit, $data);

        return redirect()->route('kits.edit', $kit)->with('success', 'Kit template updated.');
    }

    public function destroy(Kit $kit): RedirectResponse
    {
        $this->authorize('kits.manage');

        $kit->delete();

        return redirect()->route('kits.index')->with('success', 'Kit template deleted.');
    }

    // -------------------------------------------------------------- slots + assets

    public function storeItem(Request $request, Kit $kit): RedirectResponse
    {
        $this->authorize('kits.manage');

        $data = $request->validate([
            'label'       => 'required|string|max:255',
            'category_id' => 'nullable|exists:asset_categories,id',
            'quantity'    => 'required|integer|min:1',
        ]);

        $this->kits->addItem($kit, $data);

        return back()->with('success', 'Slot added.');
    }

    public function destroyItem(Kit $kit, KitItem $item): RedirectResponse
    {
        $this->authorize('kits.manage');

        $this->kits->deleteItem($item);

        return back()->with('success', 'Slot removed.');
    }

    public function linkAsset(Request $request, Kit $kit, KitItem $item): RedirectResponse
    {
        $this->authorize('kits.manage');

        $data = $request->validate(['asset_id' => 'required|exists:assets,id']);
        $this->kits->linkAsset($item, Asset::findOrFail($data['asset_id']));

        return back()->with('success', 'Asset linked to slot.');
    }

    public function unlinkAsset(Kit $kit, KitItem $item, KitAsset $kitAsset): RedirectResponse
    {
        $this->authorize('kits.manage');

        $this->kits->unlinkAsset($kitAsset);

        return back()->with('success', 'Asset unlinked.');
    }

    private function rules(?Kit $kit = null): array
    {
        return [
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:100|unique:kits,code' . ($kit ? ",{$kit->id}" : ''),
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ];
    }

    private function lookups(): array
    {
        return [
            'categories' => AssetCategory::orderBy('name')->get(),
            'assets'     => Asset::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
