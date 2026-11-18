<?php

namespace App\Models;

use App\Models\Concerns\ScopedByAssetCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaintenanceRecord extends Model
{
    use HasFactory, LogsActivity, ScopedByAssetCompany;

    protected $fillable = [
        'asset_id', 'maintenance_type_id', 'status',
        'scheduled_date', 'repeat_every_months', 'performed_date', 'vendor', 'cost', 'description',
        'is_capitalized', 'capitalized_amount', 'additional_useful_life_months',
        'previous_status_id', 'logged_by',
    ];

    /** The depreciation change this record raised by being capitalised (latest attempt). */
    public function capitalizationRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DepreciationSettingRequest::class, 'source_maintenance_id')->latestOfMany();
    }

    /** pending | applied | rejected, or null when not capitalised / not submitted yet. */
    public function capitalizationStatus(): ?string
    {
        return match ($this->capitalizationRequest?->status) {
            'pending_approval', 'approved' => 'pending',
            'applied'                      => 'applied',
            'rejected'                     => 'rejected',
            default                        => null,
        };
    }

    /** Once a capitalisation is pending or applied its amount and extra life are fixed. */
    public function capitalizationLocked(): bool
    {
        return in_array($this->capitalizationStatus(), ['pending', 'applied'], true);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date'                => 'date',
            'performed_date'                => 'date',
            'cost'                          => 'decimal:2',
            'is_capitalized'                => 'boolean',
            'capitalized_amount'            => 'decimal:2',
            'additional_useful_life_months' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function previousStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'previous_status_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('maintenance');
    }
}
