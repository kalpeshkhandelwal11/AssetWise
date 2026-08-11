<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Generic PDF wrapper shared by every report — each Export class (headings()/collection()/
 * map()) is the single source of columns for both Excel and PDF, so there's no bespoke
 * print Blade view per report.
 */
class ReportPdfExporter
{
    public function render(string $title, WithHeadings&WithMapping $export): PdfDocument
    {
        $rows = collect($export->collection())->map(fn ($item) => $export->map($item));

        return Pdf::loadView('modules.reports.pdf.generic-table', [
            'title'    => $title,
            'headings' => $export->headings(),
            'rows'     => $rows,
        ])->setPaper('a4', 'landscape');
    }
}
