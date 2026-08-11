<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Exports\AuditCampaignExport;
use App\Models\AuditCampaign;
use App\Models\ExportLog;
use App\Services\Reports\ReportPdfExporter;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class AuditReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportPdfExporter $pdfExporter,
    ) {
    }

    public function show(AuditCampaign $campaign): View
    {
        $this->authorize('audit.manage');

        $filters = ['campaign_id' => $campaign->id];

        return view('modules.audits.report', [
            'campaign' => $campaign->load('auditType'),
            'rows'     => $this->reports->buildAuditCampaignQuery($filters)->get(),
        ]);
    }

    public function export(Request $request, AuditCampaign $campaign): Response
    {
        $this->authorize('audit.manage');

        $format = $request->input('format') === 'pdf' ? 'pdf' : 'xlsx';
        $filters = ['campaign_id' => $campaign->id];

        $export = new AuditCampaignExport($filters, $this->reports);
        $fileName = "audit-campaign-{$campaign->id}-".now()->format('Ymd-His').".{$format}";

        ExportLog::record('audit_campaign', $filters, $export->rowCount(), $fileName, $request->user()->id);

        return $format === 'pdf'
            ? $this->pdfExporter->render("Audit Campaign — {$campaign->name}", $export)->download($fileName)
            : Excel::download($export, $fileName);
    }
}
