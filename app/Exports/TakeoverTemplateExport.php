<?php

namespace App\Exports;

use App\Models\PlazaTakeover;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank takeover checklist in the shape of the NHAI handover document (TP-02): one row per
 * item — quantity rows for building/booth items, one row per serial for lane equipment.
 * Purchase takeovers add unit_price.
 */
class TakeoverTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private readonly PlazaTakeover $takeover)
    {
    }

    public function headings(): array
    {
        return array_values(array_filter([
            'section', 'item_name', 'category', 'asset_type', 'make', 'model', 'quantity',
            'serial_number', 'position', $this->takeover->isNhai() ? null : 'unit_price',
            'building', 'room', 'location_note', 'condition', 'not_functional_qty', 'warranty_years', 'remarks',
        ]));
    }

    public function array(): array
    {
        $examples = [
            ['Toll Building', 'SAMPLE — Revolving chair', 'Furniture & Fixtures', '', 'Nilkamal', '', 17, '', '', 2500, '', '', 'Toll Manager Room', 'functional', 0, '', ''],
            ['Hybrid ETC Lanes', 'SAMPLE — AVC camera', 'Electrical Installation', '', 'Sparsh', 'SC-IS82BP-I(S)', 1, 'SCQX20526899982', 'Lane 1 (Booth)', 85000, '', '', '', 'not_functional', 1, 5, 'Lane closed'],
        ];

        if ($this->takeover->isNhai()) {
            $examples = array_map(function ($row) {
                unset($row[9]);

                return array_values($row);
            }, $examples);
        }

        return $examples;
    }
}
