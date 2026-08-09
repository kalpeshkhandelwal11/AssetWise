<?php

namespace App\Http\Controllers\Assets;

use App\Exports\AssetExport;
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
            'companies'  => Company::orderBy('name')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
            'types'      => AssetType::orderBy('name')->get(),
            'statuses'   => AssetStatus::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('assets.export');

        $filters = $request->only(self::FILTER_KEYS);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $count = (new AssetExport($filters, $this->fields))->buildQuery()->count();

        if ($count <= self::INLINE_ROW_THRESHOLD) {
            $export = new AssetExport($filters, $this->fields);
            $fileName = 'assets-export-'.now()->format('Ymd-His').'.xlsx';
            ExportLog::record('assets', $filters, $export->rowCount(), $fileName);

            return Excel::download($export, $fileName);
        }

        GenerateAssetExport::dispatch($request->user()->id, $filters);

        return redirect()->route('assets.export.index')
            ->with('success', "Export queued for {$count} rows — you'll be notified when it's ready.");
    }
}
