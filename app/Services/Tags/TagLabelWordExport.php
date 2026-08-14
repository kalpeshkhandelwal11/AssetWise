<?php

namespace App\Services\Tags;

use Illuminate\Support\Collection;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Word (.docx) label sheet — same rendered image set as the PDF path, laid out in a
 * table via PhpWord and streamed as a download.
 */
class TagLabelWordExport
{
    public function __construct(private readonly TagLabelRenderer $renderer)
    {
    }

    /** @param  Collection<int, \App\Models\Tag>  $tags */
    public function download(Collection $tags, string $filename): StreamedResponse
    {
        $rendered = $this->renderer->render($tags);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Asset Tags', ['bold' => true, 'size' => 16]);

        $columns = 3;
        $rows = $rendered->chunk($columns);

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);

        foreach ($rows as $row) {
            $table->addRow();

            foreach ($row as $entry) {
                $cell = $table->addCell(3000);

                if ($entry['mime'] === 'image/png') {
                    // PhpWord's SOURCE_STRING path expects the raw binary, not a data:
                    // URI — decode the base64 the renderer produced (the PDF path embeds
                    // that same base64 as-is via a data: URI in an <img> tag).
                    $cell->addImage(base64_decode($entry['image']), ['width' => 100, 'height' => 100, 'align' => 'center']);
                } else {
                    // QR labels render as SVG (see TagLabelRenderer) and PhpWord's Word
                    // writer has no SVG-to-raster path without imagick, so the .docx sheet
                    // falls back to the scan URL as text for QR-type tags.
                    $cell->addText($entry['tag']->qr_payload ?: url("/scan/{$entry['tag']->tag_number}"), ['size' => 8, 'italic' => true], ['alignment' => 'center']);
                }

                $cell->addText($entry['tag']->tag_number, ['size' => 10, 'bold' => true], ['alignment' => 'center']);
            }

            // Pad the row so every table row has the same cell count.
            for ($i = $row->count(); $i < $columns; $i++) {
                $table->addCell(3000);
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
