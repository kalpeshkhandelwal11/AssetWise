<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DisposalType;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Models\Priority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        'departments'       => ['model' => Department::class,     'label' => 'Departments',  'permission' => 'departments.manage',  'usage' => [['users', 'department_id'], ['assets', 'department_id']]],
        'branches'          => ['model' => Branch::class,         'label' => 'Branches',     'permission' => 'branches.manage',     'usage' => [['users', 'branch_id'], ['assets', 'branch_id']]],
        'designations'      => ['model' => Designation::class,    'label' => 'Designations', 'permission' => 'designations.manage', 'usage' => [['users', 'designation_id']]],
    ];

    private function resolveEntity(string $entity): array
    {
        abort_unless(array_key_exists($entity, self::ENTITIES), 404);
        return self::ENTITIES[$entity];
    }

    public function landing(): View
    {
        $entities = collect(self::ENTITIES)
            ->filter(fn ($cfg) => auth()->user()->can($cfg['permission'] ?? 'masters.manage'))
            ->map(fn ($cfg, $slug) => [
                'slug'  => $slug,
                'label' => $cfg['label'],
                'count' => $cfg['model']::count(),
            ])->values();

        abort_if($entities->isEmpty(), 403);

        return view('admin.masters.landing', compact('entities'));
    }

    public function index(Request $request, string $entity): View
    {
        $cfg = $this->resolveEntity($entity);
        $this->authorize($cfg['permission'] ?? 'masters.manage');

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
        $cfg = $this->resolveEntity($entity);
        $this->authorize($cfg['permission'] ?? 'masters.manage');

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
        $cfg = $this->resolveEntity($entity);
        $this->authorize($cfg['permission'] ?? 'masters.manage');

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
        $cfg = $this->resolveEntity($entity);
        $this->authorize($cfg['permission'] ?? 'masters.manage');

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
        $cfg = $this->resolveEntity($entity);
        $this->authorize($cfg['permission'] ?? 'masters.manage');

        $item = $cfg['model']::findOrFail($id);

        if (! empty($cfg['has_system']) && $item->is_system) {
            return back()->with('error', 'System records cannot be deleted.');
        }

        foreach ($cfg['usage'] ?? [] as [$table, $column]) {
            $count = DB::table($table)->where($column, $id)->count();
            if ($count > 0) {
                return back()->with('error', "In use by {$count} record(s). Deactivate instead.");
            }
        }

        $item->delete();

        return redirect()->route('admin.masters.index', $entity)
            ->with('success', 'Record deleted.');
    }
}
