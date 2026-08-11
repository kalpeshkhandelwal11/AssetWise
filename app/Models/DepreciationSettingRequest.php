<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DepreciationSettingRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_id', 'depreciation_method_id',
        'useful_life_months', 'salvage_value', 'salvage_percent',
        'start_date', 'cost_basis',
        'change_type', 'source_maintenance_id',
        'status', 'approval_request_id', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_months' => 'integer',
            'salvage_value'      => 'decimal:2',
            'salvage_percent'    => 'decimal:4',
            'start_date'         => 'date',
            'cost_basis'         => 'decimal:2',
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

    public function sourceMaintenance(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class, 'source_maintenance_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Consumed by ApprovalRequest::getApprovableLabelAttribute() for the approver inbox. */
    public function getApprovalLabel(): string
    {
        return 'Depreciation — ' . ($this->asset?->name ?? 'Asset #' . $this->asset_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('depreciation');
    }
}
