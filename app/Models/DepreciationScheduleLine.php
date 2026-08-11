<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationScheduleLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'asset_depreciation_setting_id',
        'period_year', 'period_month', 'days_in_period',
        'opening_book_value', 'depreciation_amount',
        'accumulated_depreciation', 'closing_book_value',
        'status', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year'              => 'integer',
            'period_month'             => 'integer',
            'days_in_period'           => 'integer',
            'opening_book_value'       => 'decimal:2',
            'depreciation_amount'      => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'closing_book_value'       => 'decimal:2',
            'posted_at'                => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(AssetDepreciationSetting::class, 'asset_depreciation_setting_id');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
