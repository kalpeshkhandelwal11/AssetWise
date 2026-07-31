<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditType;
use App\Models\DisposalType;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Models\Priority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterController extends Controller
{
    /** Map of URL slug → [model class, human label, extra config]. */
    private const ENTITIES = [
        'statuses'          => ['model' => AssetStatus::class,    'label' => 'Asset Statuses',      'has_color' => true,  'has_system' => true],
        'asset-types'       => ['model' => AssetType::class,      'label' => 'Asset Types'],
        'priorities'        => ['model' => Priority::class,       'label' => 'Priorities'],
        'movement-types'    => ['model' => MovementType::class,   'label' => 'Movement Types'],
        'audit-types'       => ['model' => AuditType::class,      'label' => 'Audit Types'],
        'disposal-types'    => ['model' => DisposalType::class,   'label' => 'Disposal Types'],
        'maintenance-types' => ['model' => MaintenanceType::class,'label' => 'Maintenance Types'],
    ];

    private function resolveEntity(string $entity): array
    {
        abort_unless(array_key_exists($entity, self::ENTITIES), 404);
        return self::ENTITIES[$entity];
    }

    public function landing(): View
    {
        $this->authorize('masters.manage');

        $entities = collect(self::ENTITIES)->map(fn ($cfg, $slug) => [
            'slug'  => $slug,
            'label' => $cfg['label'],
            'count' => $cfg['model']::count(),
        ])->values();

        return view('admin.masters.landing', compact('entities'));
    }

    public function index(Request $request, string $entity): View
    {
        $this->authorize('masters.manage');

        $cfg   = $this->resolveEntity($entity);
        $model = $cfg['model'];

        $query = $model::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $items = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.masters.index', [
            'entity'  => $entity,
            'cfg'     => $cfg,
            'items'   => $items,
        ]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $this->authorize('masters.manage');

        $cfg = $this->resolveEntity($entity);

        $rules = [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|alpha_dash|unique:' . (new $cfg['model'])->getTable() . ',code',
        ];

        if (! empty($cfg['has_color'])) {
            $rules['color'] = 'nullable|string|max:20';
        }

        $data = $request->validate($rules);
        $data['code'] = strtoupper($data['code']);

        if (! empty($cfg['has_color'])) {
            $data['color'] = $request->input('color', '#6b7280');
        }

        $cfg['model']::create($data);

        return redirect()->route('admin.masters.index', $entity)
            ->with('success', rtrim($cfg['label'], 's') . ' created.');
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->authorize('masters.manage');

        $cfg  = $this->resolveEntity($entity);
        $item = $cfg['model']::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|alpha_dash|unique:' . $item->getTable() . ',code,' . $id,
        ];

        if (! empty($cfg['has_color'])) {
            $rules['color'] = 'nullable|string|max:20';
        }

        $data = $request->validate($rules);
        $data['code'] = strtoupper($data['code']);

        $item->update($data);

        return redirect()->route('admin.masters.index', $entity)
            ->with('success', rtrim($cfg['label'], 's') . ' updated.');
    }

    public function toggleActive(string $entity, int $id): RedirectResponse
    {
        $this->authorize('masters.manage');

        $cfg  = $this->resolveEntity($entity);
        $item = $cfg['model']::findOrFail($id);

        // System statuses cannot be deactivated
        if (! empty($cfg['has_system']) && $item->is_system) {
            return back()->with('error', 'System statuses cannot be deactivated.');
        }

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(string $entity, int $id): RedirectResponse
    {
        $this->authorize('masters.manage');

        $cfg  = $this->resolveEntity($entity);
        $item = $cfg['model']::findOrFail($id);

        if (! empty($cfg['has_system']) && $item->is_system) {
            return back()->with('error', 'System records cannot be deleted.');
        }

        $item->delete();

        return redirect()->route('admin.masters.index', $entity)
            ->with('success', 'Record deleted.');
    }
}
