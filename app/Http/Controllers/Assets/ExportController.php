<?php

namespace App\Http\Controllers\Assets;

use App\Exports\AssetExport;
use App\Exports\AssetPdfExport;
use App\Services\Reports\ReportPdfExporter;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAssetExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\ExportLog;
use App\Services\DynamicFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /** Exports at or below this row count stream immediately instead of being queued. */
    private const INLINE_ROW_THRESHOLD = 500;

    private const FILTER_KEYS = [
        'search', 'company_id', 'category_id', 'asset_type_id', 'status_id',
        'location_id', 'custodian_id', 'department_id', 'branch_id',
        'date_from', 'date_to', 'show_deleted',
    ];

    public function __construct(private readonly DynamicFieldService $fields)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('assets.export');

        return view('modules.assets.export.index', [
            'companies'  => Company::visible()->orderBy('name')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
            'types'      => AssetType::orderBy('name')->get(),
            'statuses'   => AssetStatus::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('assets.export');

        $format = $request->input('format') === 'pdf' ? 'pdf' : 'xlsx';

        $filters = $request->only(self::FILTER_KEYS);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        // Bulk "Export selected" posts asset_ids[]; feed them through as the ids filter.
        if ($request->filled('asset_ids')) {
            $filters['ids'] = array_map('intval', (array) $request->input('asset_ids'));
        }

        $count = (new AssetExport($filters, $this->fields))->buildQuery()->count();

        if ($format === 'pdf' && $count > AssetPdfExport::MAX_ROWS) {
            return back()->with('error', "PDF is limited to ".AssetPdfExport::MAX_ROWS." assets ({$count} match). Narrow the filters or export to Excel.");
        }

        if ($count <= self::INLINE_ROW_THRESHOLD) {
            $fileName = 'assets-export-'.now()->format('Ymd-His').".{$format}";

            if ($format === 'pdf') {
                $export = new AssetPdfExport($filters, $this->fields);
                ExportLog::record('assets', $filters, $export->rowCount(), $fileName);

                return app(ReportPdfExporter::class)->render('Asset Export', $export)->download($fileName);
            }

            $export = new AssetExport($filters, $this->fields);
            ExportLog::record('assets', $filters, $export->rowCount(), $fileName);

            return Excel::download($export, $fileName);
        }

        GenerateAssetExport::dispatch($request->user()->id, $filters, $format);

        return redirect()->route('assets.export.index')
            ->with('success', "Export queued for {$count} rows — you'll be notified when it's ready.");
    }
}
