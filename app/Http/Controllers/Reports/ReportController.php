<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AuditCampaign;
use App\Models\Company;
use App\Models\DisposalType;
use App\Models\ExportLog;
use App\Models\MovementType;
use App\Models\User;
use App\Services\Reports\ReportPdfExporter;
use App\Services\Reports\ReportRegistry;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /** Exports at or below this row count stream immediately instead of being queued — mirrors Assets\ExportController. */
    private const INLINE_ROW_THRESHOLD = 500;

    /** Filters accepted per report type — whitelisted from the request, same shape ReportService expects. */
    private const FILTER_KEYS = [
        'asset_register'        => ['company_id', 'category_id', 'asset_type_id', 'status_id', 'location_id', 'department_id', 'branch_id', 'search'],
        'movement'               => ['company_id', 'movement_type_id', 'status', 'date_from', 'date_to', 'search'],
        'intercompany_transfer'  => ['from_company_id', 'to_company_id', 'date_from', 'date_to', 'search'],
        'disposal'               => ['company_id', 'disposal_type_id', 'status', 'date_from', 'date_to', 'search'],
        'aging'                  => ['company_id', 'category_id', 'status_id'],
        'utilization'            => ['company_id'],
        'audit_compliance'       => ['company_id', 'status', 'changed_by', 'date_from', 'date_to'],
        'audit_campaign'          => ['campaign_id', 'company_id', 'status', 'search', 'date_from', 'date_to'],
        'depreciation_schedule'  => ['company_id', 'category_id', 'department_id', 'status', 'search'],
    ];

    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportPdfExporter $pdfExporter,
    ) {
    }

    public function index(): View
    {
        $this->authorize('reports.view');

        return view('modules.reports.index', ['reportTypes' => ReportRegistry::all()]);
    }

    public function show(Request $request, string $type): View
    {
        $this->authorize('reports.view');
        ReportRegistry::assertEnabled($type);

        $filters = $request->only(self::FILTER_KEYS[$type] ?? []);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $rows = $type === 'utilization'
            ? $this->reports->buildUtilizationRows($filters)
            : $this->reports->queryFor($type, $filters)->paginate(20)->withQueryString();

        return view("modules.reports.show", [
            'type'    => $type,
            'label'   => ReportRegistry::label($type),
            'rows'    => $rows,
            'filters' => $filters,
        ] + $this->lookups());
    }

    public function export(Request $request, string $type): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('reports.export');
        ReportRegistry::assertEnabled($type);

        $format = $request->input('format') === 'pdf' ? 'pdf' : 'xlsx';
        $filters = $request->only(self::FILTER_KEYS[$type] ?? []);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $export = $this->reports->exportFor($type, $filters);
        $count = $export->rowCount();

        if ($count <= self::INLINE_ROW_THRESHOLD) {
            $fileName = "{$type}-report-".now()->format('Ymd-His').".{$format}";
            ExportLog::record($type, $filters, $count, $fileName);

            return $format === 'pdf'
                ? $this->pdfExporter->render(ReportRegistry::label($type), $export)->download($fileName)
                : Excel::download($export, $fileName);
        }

        GenerateReportExport::dispatch($request->user()->id, $type, $filters, $format);

        return redirect()->route('reports.show', $type)
            ->with('success', "Export queued for {$count} rows — you'll be notified when it's ready.");
    }

    private function lookups(): array
    {
        return [
            'companies'     => Company::orderBy('name')->get(),
            'categories'    => AssetCategory::orderBy('name')->get(),
            'statuses'      => AssetStatus::orderBy('name')->get(),
            'movementTypes' => MovementType::where('is_active', true)->orderBy('name')->get(),
            'disposalTypes' => DisposalType::where('is_active', true)->orderBy('name')->get(),
            'users'         => User::where('is_active', true)->orderBy('name')->get(),
            'auditCampaigns' => AuditCampaign::orderBy('name')->get(),
        ];
    }
}
