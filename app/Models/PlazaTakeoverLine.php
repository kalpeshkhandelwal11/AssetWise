<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist row. A quantity row ("Revolving chairs × 17") expands into 17 assets on submit;
 * a serialized row (one lane's AVC camera) is quantity 1 with its serial number.
 */
class PlazaTakeoverLine extends Model
{
    public const CONDITIONS = [
        'functional'           => 'Functional / available',
        'partially_functional' => 'Partially functional',
        'not_functional'       => 'Not functional',
    ];

    protected $fillable = [
        'takeover_id', 'line_no', 'section', 'item_name', 'category_id', 'asset_type_id',
        'manufacturer', 'model', 'quantity', 'serial_number', 'position_label', 'unit_price',
        'building_id', 'floor_id', 'room_id', 'location_note', 'condition', 'not_functional_qty',
        'warranty_years', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity'           => 'integer',
            'not_functional_qty' => 'integer',
            'unit_price'         => 'decimal:2',
            'warranty_years'     => 'decimal:1',
        ];
    }

    public function takeover(): BelongsTo
    {
        return $this->belongsTo(PlazaTakeover::class, 'takeover_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
