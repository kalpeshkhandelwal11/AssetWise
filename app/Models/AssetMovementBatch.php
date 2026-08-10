<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groups N asset_movements rows behind a single approval (P9.1 — bulk multi-select gets
 * one approval per batch, not one per asset). Also the reuse target for M17's future kit
 * assignments: a kit-assignment batch is the same model with kit_assignment_id set,
 * applied via the same MovementService::applyBulk().
 */
class AssetMovementBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_type_id', 'to_company_id', 'to_location_id', 'to_custodian_id',
        'to_department_id', 'to_status_id', 'kit_assignment_id', 'status', 'notes',
        'approval_request_id', 'requested_by',
    ];

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MovementType::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'batch_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_custodian_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'to_status_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Forward-compat hook consumed by ApprovalRequest::getApprovableLabelAttribute(). */
    public function getApprovalLabel(): string
    {
        $count = $this->relationLoaded('movements') ? $this->movements->count() : $this->movements()->count();

        return $this->movementType?->name . " — {$count} asset(s)";
    }
}
