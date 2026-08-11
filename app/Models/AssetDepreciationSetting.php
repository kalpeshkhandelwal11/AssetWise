<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetDepreciationSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_id', 'depreciation_method_id',
        'useful_life_months', 'salvage_value', 'salvage_percent',
        'start_date', 'cost_basis',
        'accumulated_depreciation', 'current_book_value',
        'is_active', 'stopped_at', 'superseded_by',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_months'       => 'integer',
            'salvage_value'            => 'decimal:2',
            'salvage_percent'          => 'decimal:4',
            'start_date'               => 'date',
            'cost_basis'               => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'current_book_value'       => 'decimal:2',
            'is_active'                => 'boolean',
            'stopped_at'               => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(DepreciationMethod::class, 'depreciation_method_id');
    }

    public function scheduleLines(): HasMany
    {
        return $this->hasMany(DepreciationScheduleLine::class, 'asset_depreciation_setting_id')
            ->orderBy('period_year')
            ->orderBy('period_month');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('depreciation');
    }
}
