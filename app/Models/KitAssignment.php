<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KitAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'kit_id', 'direction', 'parent_assignment_id',
        'movement_type_id', 'to_company_id', 'to_custodian_id', 'to_location_id', 'to_department_id',
        'approval_mode', 'status', 'approval_request_id', 'requested_by',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MovementType::class);
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_custodian_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Single-mode grouping: the batch whose kit_assignment_id points back here. */
    public function batch(): HasOne
    {
        return $this->hasOne(AssetMovementBatch::class, 'kit_assignment_id');
    }

    /** All movements for this assignment (single or per_asset), via the denormalized column. */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'kit_assignment_id');
    }

    /** Consumed by ApprovalRequest::getApprovableLabelAttribute() for the approver inbox. */
    public function getApprovalLabel(): string
    {
        $label = $this->kit?->name ?? 'Ad-hoc bundle';

        return ($this->direction === 'return' ? 'Kit return' : 'Kit assignment') . ' — ' . $label;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('kit');
    }
}
