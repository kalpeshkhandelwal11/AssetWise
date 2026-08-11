<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CategoryDepreciationDefault extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'category_id', 'depreciation_method_id',
        'useful_life_months', 'salvage_value', 'salvage_percent',
        'start_basis',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_months' => 'integer',
            'salvage_value'      => 'decimal:2',
            'salvage_percent'    => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(DepreciationMethod::class, 'depreciation_method_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('depreciation');
    }
}
