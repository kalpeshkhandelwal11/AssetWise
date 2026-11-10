<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hidden sheet holding one column per dropdown source list. Data validation references
 * ranges here instead of inline lists, which Excel caps at 255 characters.
 */
class TemplateListsSheet implements FromArray, WithTitle, WithEvents
{
    public const TITLE = 'Lists';

    /** @param array<string, string[]> $lists heading => values, in column order */
    public function __construct(private readonly array $lists)
    {
    }

    public function title(): string
    {
        return self::TITLE;
    }

    public function array(): array
    {
        $columns = array_values($this->lists);
        $rows = [array_keys($this->lists)];
        $height = $columns ? max(array_map('count', $columns)) : 0;

        for ($i = 0; $i < $height; $i++) {
            $rows[] = array_map(fn (array $col) => $col[$i] ?? null, $columns);
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $event->sheet->getDelegate()
                ->setSheetState(Worksheet::SHEETSTATE_HIDDEN),
        ];
    }
}
