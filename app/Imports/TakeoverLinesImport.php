<?php

namespace App\Imports;

use App\Imports\Support\MasterLookup;
use App\Models\Building;
use App\Models\PlazaTakeover;
use App\Models\PlazaTakeoverLine;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads a takeover checklist (TakeoverTemplateExport layout) into line arrays for
 * TakeoverService::addLines(). Masters by name/code (MasterLookup); room/building by name or
 * code within the plaza. All-or-nothing: any bad row throws with every row's errors listed.
 */
class TakeoverLinesImport implements ToCollection, WithHeadingRow, WithMultipleSheets, SkipsUnknownSheets
{
    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    private MasterLookup $lookup;

    public function __construct(private readonly PlazaTakeover $takeover)
    {
        $this->lookup = new MasterLookup;
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function onUnknownSheet($sheetName): void
    {
    }

    public function collection(Collection $rows): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();
            $rowNumber = $index + 2;

            if (blank($row['item_name'] ?? null) || Str::startsWith((string) $row['item_name'], 'SAMPLE')) {
                continue;
            }

            $rowErrors = [];
            $category = $this->lookup->resolve('category', $row['category'] ?? null);
            $type = $this->lookup->resolve('asset_type', $row['asset_type'] ?? null);

            if ($category['error'] || ! $category['id']) {
                $rowErrors[] = $category['error'] ?? 'Category is required.';
            }
            if ($type['error']) {
                $rowErrors[] = $type['error'];
            }

            $quantity = (int) ($row['quantity'] ?? 1) ?: 1;
            $price = $row['unit_price'] ?? null;

            if (! $this->takeover->isNhai() && ($price === null || $price === '' || ! is_numeric($price))) {
                $rowErrors[] = 'Unit price is required for a purchase.';
            }
            if ($this->takeover->isNhai() && filled($price)) {
                $rowErrors[] = 'A takeover from NHAI carries no price — leave unit_price empty.';
            }
            if (filled($row['serial_number'] ?? null) && $quantity > 1) {
                $rowErrors[] = 'A serial number needs quantity 1.';
            }

            $condition = $this->condition($row['condition'] ?? null);
            if ($condition === null) {
                $rowErrors[] = 'Condition must be functional, partially functional or not functional.';
            }

            [$buildingId, $floorId, $roomId] = $this->place($row);

            if ($rowErrors) {
                $errors[] = "Row {$rowNumber}: ".implode(' ', $rowErrors);

                continue;
            }

            $this->lines[] = [
                'section'            => $row['section'] ?? null,
                'item_name'          => trim((string) $row['item_name']),
                'category_id'        => $category['id'],
                'asset_type_id'      => $type['id'],
                'manufacturer'       => $row['make'] ?? $row['manufacturer'] ?? null,
                'model'              => $row['model'] ?? null,
                'quantity'           => $quantity,
                'serial_number'      => filled($row['serial_number'] ?? null) ? trim((string) $row['serial_number']) : null,
                'position_label'     => $row['position'] ?? null,
                'unit_price'         => $this->takeover->isNhai() ? null : (float) $price,
                'building_id'        => $buildingId,
                'floor_id'           => $floorId,
                'room_id'            => $roomId,
                'location_note'      => $row['location_note'] ?? null,
                'condition'          => $condition,
                'not_functional_qty' => (int) ($row['not_functional_qty'] ?? 0),
                'warranty_years'     => filled($row['warranty_years'] ?? null) ? (float) $row['warranty_years'] : null,
                'remarks'            => $row['remarks'] ?? null,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
    }

    private function condition(mixed $raw): ?string
    {
        $value = Str::of((string) $raw)->lower()->replace(['-', ' '], '_')->trim('_')->value();

        return match ($value) {
            '', 'functional', 'available', 'working', 'ok'                     => 'functional',
            'partially_functional', 'partial', 'partially_working'            => 'partially_functional',
            'not_functional', 'not_available', 'not_working', 'non_functional' => 'not_functional',
            default                                                           => null,
        };
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} building, floor, room ids — matched inside the plaza. */
    private function place(array $row): array
    {
        $buildingIds = Building::where('location_id', $this->takeover->location_id)->pluck('id');
        $match = fn ($q, $raw) => $q->where(fn ($w) => $w
            ->whereRaw('LOWER(name) = ?', [MasterLookup::normalise($raw)])
            ->orWhereRaw('LOWER(code) = ?', [MasterLookup::normalise($raw)]));

        $building = filled($row['building'] ?? null)
            ? $match(Building::whereIn('id', $buildingIds), $row['building'])->first()
            : null;

        $room = filled($row['room'] ?? null)
            ? $match(Room::whereHas('floor', fn ($q) => $q->whereIn('building_id', $building ? [$building->id] : $buildingIds)), $row['room'])->first()
            : null;

        return [$building?->id ?? $room?->floor?->building_id, $room?->floor_id, $room?->id];
    }
}
