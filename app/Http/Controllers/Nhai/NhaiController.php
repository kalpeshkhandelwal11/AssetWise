<?php

namespace App\Http\Controllers\Nhai;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Location;
use App\Models\PlazaHandover;
use App\Models\PlazaTakeover;
use App\Services\Reports\ReportPdfExporter;
use App\Support\CompanyVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Handover / Takeover page: a dashboard of the toll plazas that work with NHAI (what each
 * holds — NHAI-owned and ours), the NHAI asset list behind each plaza ("Details"), and the
 * takeover and handover transactions. Company isolation applies — a user sees and counts only
 * the assets of the plazas their companies operate.
 */
class NhaiController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('nhai.view');

        $tab = in_array($request->input('tab'), ['plazas', 'assets', 'takeovers', 'handovers'], true) ? $request->input('tab') : 'plazas';
        $visible = CompanyVisibility::ids();

        return view('modules.nhai.index', [
            'tab'        => $tab,
            'dashboard'  => $tab === 'plazas' ? $this->plazaDashboard($request, $visible) : null,
            'plaza'      => $tab === 'assets' && $request->filled('location_id') ? Location::find($request->integer('location_id')) : null,
            'managers'   => $tab === 'plazas' ? \App\Models\Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code', 'user_id']) : collect(),
            'assets'     => $tab === 'assets' ? $this->assetQuery($request)->with(['location', 'category', 'status', 'custodian', 'room'])->orderBy('name')->paginate(50)->withQueryString() : null,
            'takeovers'  => $tab === 'takeovers' ? PlazaTakeover::with(['location', 'company', 'fromCompany'])->withCount('lines')
                ->when($visible !== null, fn ($q) => $q->whereIn('company_id', $visible))->latest('id')->paginate(25) : null,
            'handovers'  => $tab === 'handovers' ? PlazaHandover::with(['location', 'company'])->withCount('lines')
                ->when($visible !== null, fn ($q) => $q->whereIn('company_id', $visible))->latest('id')->paginate(25) : null,
            'plazas'     => Location::where('is_active', true)->orderByDesc('is_toll_plaza')->orderBy('name')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
            'statuses'   => AssetStatus::orderBy('name')->get(),
            'filters'    => $request->only(['location_id', 'category_id', 'status_id', 'condition', 'search']),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('nhai.view');

        $export = new class($this->assetQuery($request)->with(['location', 'category', 'status', 'custodian', 'room'])->orderBy('name')->get()) implements FromCollection, WithHeadings, WithMapping
        {
            public function __construct(private $rows) {}

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Asset Tag', 'Item', 'Make', 'Model', 'Serial', 'Plaza', 'Room / Lane', 'Category', 'Condition', 'Status', 'Custodian', 'Remarks'];
            }

            public function map($a): array
            {
                return [$a->asset_tag, $a->name, $a->manufacturer, $a->model, $a->serial_number, $a->location?->name,
                    $a->room?->name, $a->category?->name, \App\Models\PlazaTakeoverLine::CONDITIONS[$a->condition] ?? $a->condition,
                    $a->status?->name, $a->custodian?->name, $a->condition_remarks];
            }
        };

        $file = 'nhai-assets-'.now()->format('Ymd-His');

        return $request->input('format') === 'pdf'
            ? app(ReportPdfExporter::class)->render('Assets received from NHAI', $export)->download("{$file}.pdf")
            : Excel::download($export, "{$file}.xlsx");
    }

    /** Assets owned by the authority (NHAI). The Asset scope adds company isolation. */
    /**
     * Per NHAI toll plaza: live NHAI-owned and our-own asset counts, NHAI count per category,
     * not-working count (condition at takeover), latest takeover and handover. A few grouped
     * queries for the whole page, never one per plaza. Retired assets (returned, handed over,
     * sold…) no longer count; deleted ones are excluded by the soft-delete scope.
     *
     * @param  int[]|null  $visible  company ids the user may see (null = all)
     */
    private function plazaDashboard(Request $request, ?array $visible): array
    {
        $plazas = Location::query()
            ->where('is_active', true)->where('is_nhai_plaza', true)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('code', 'like', '%'.$request->input('search').'%')))
            ->with('manager')
            ->orderBy('name')
            ->get();
        $ids = $plazas->pluck('id');

        $nhaiCompanies = \App\Models\Company::where('party_type', 'external_authority')->pluck('id');
        $retired = AssetStatus::whereIn('code', AssetStatus::TERMINAL)->pluck('id');

        // Live assets at these plazas (company isolation from the Asset global scope).
        $live = fn () => Asset::query()->whereIn('location_id', $ids)->whereNotIn('status_id', $retired);

        $count = fn ($query) => $query->selectRaw('location_id, count(*) as total')->groupBy('location_id')->pluck('total', 'location_id');

        $nhaiCount = $count($live()->whereIn('company_id', $nhaiCompanies));
        $oursCount = $count($live()->whereNotIn('company_id', $nhaiCompanies));
        $notWorking = $count($live()->whereIn('company_id', $nhaiCompanies)->whereIn('condition', ['not_functional', 'partially_functional']));

        $byCategory = $live()->whereIn('company_id', $nhaiCompanies)
            ->selectRaw('location_id, category_id, count(*) as total')
            ->groupBy('location_id', 'category_id')->get()
            ->groupBy('location_id');
        $oursByCategory = $live()->whereNotIn('company_id', $nhaiCompanies)
            ->selectRaw('location_id, category_id, count(*) as total')
            ->groupBy('location_id', 'category_id')->get()
            ->groupBy('location_id');
        $categoryNames = AssetCategory::whereIn('id',
            $byCategory->flatten()->pluck('category_id')->merge($oursByCategory->flatten()->pluck('category_id'))->unique()
        )->pluck('name', 'id');

        $scopeCompany = fn ($q) => $q->when($visible !== null, fn ($w) => $w->whereIn('company_id', $visible));
        $latestTakeover = PlazaTakeover::whereIn('location_id', $ids)->tap($scopeCompany)->latest('id')->get()->unique('location_id')->keyBy('location_id');
        $latestHandover = PlazaHandover::whereIn('location_id', $ids)->tap($scopeCompany)->latest('id')->get()->unique('location_id')->keyBy('location_id');

        $rows = $plazas->map(function (Location $plaza) use ($nhaiCount, $oursCount, $notWorking, $byCategory, $oursByCategory, $categoryNames, $latestTakeover, $latestHandover) {
            $categories = collect();
            foreach ($byCategory->get($plaza->id, collect()) as $r) {
                $categories[$r->category_id] = ['name' => $categoryNames[$r->category_id] ?? 'Uncategorised', 'nhai' => (int) $r->total, 'ours' => 0];
            }
            foreach ($oursByCategory->get($plaza->id, collect()) as $r) {
                $row = $categories[$r->category_id] ?? ['name' => $categoryNames[$r->category_id] ?? 'Uncategorised', 'nhai' => 0, 'ours' => 0];
                $row['ours'] = (int) $r->total;
                $categories[$r->category_id] = $row;
            }

            return [
                'plaza'       => $plaza,
                'nhai'        => (int) ($nhaiCount[$plaza->id] ?? 0),
                'ours'        => (int) ($oursCount[$plaza->id] ?? 0),
                'not_working' => (int) ($notWorking[$plaza->id] ?? 0),
                'categories'  => $categories->sortBy('name')->values(),
                'takeover'    => $latestTakeover->get($plaza->id),
                'handover'    => $latestHandover->get($plaza->id),
            ];
        });

        return [
            'rows'   => $rows,
            'totals' => [
                'plazas'      => $rows->count(),
                'nhai'        => $rows->sum('nhai'),
                'ours'        => $rows->sum('ours'),
                'not_working' => $rows->sum('not_working'),
            ],
        ];
    }

    private function assetQuery(Request $request): Builder
    {
        return Asset::query()
            ->whereHas('company', fn ($q) => $q->where('party_type', 'external_authority'))
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->input('location_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('status_id'), fn ($q) => $q->where('status_id', $request->input('status_id')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->input('condition')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('serial_number', 'like', '%'.$request->input('search').'%')
                ->orWhere('asset_tag', 'like', '%'.$request->input('search').'%')));
    }
}
