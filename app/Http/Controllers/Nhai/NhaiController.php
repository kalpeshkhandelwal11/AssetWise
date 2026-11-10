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
 * The NHAI module page: assets received from NHAI (owned by the authority, operated by us),
 * plus the takeover and handover transactions. Company isolation applies — a user sees the
 * NHAI assets of the plazas their companies operate.
 */
class NhaiController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('nhai.view');

        $tab = in_array($request->input('tab'), ['assets', 'takeovers', 'handovers'], true) ? $request->input('tab') : 'assets';
        $visible = CompanyVisibility::ids();

        return view('modules.nhai.index', [
            'tab'        => $tab,
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
