<?php

namespace App\Models;

use App\Models\Concerns\ScopedByAssetCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMovement extends Model
{
    use HasFactory, LogsActivity, ScopedByAssetCompany;

    protected $fillable = [
        'asset_id', 'movement_type_id', 'batch_id', 'kit_assignment_id',
        'from_company_id', 'to_company_id',
        'from_location_id', 'to_location_id',
        'from_custodian_id', 'to_custodian_id',
        'from_department_id', 'to_department_id',
        'to_status_id', 'status', 'notes',
        'verified_at', 'verified_by',
        'verification_condition', 'verification_notes', 'verification_photos',
        'cancelled_at', 'cancelled_by',
        'approval_request_id', 'requested_by',
        // Phase 2 — receipt
        'receipt_status', 'dispatched_at', 'previous_status_id',
        'received_at', 'received_by', 'receipt_method',
        'short_type', 'short_reported_at', 'short_reported_by',
        'resolution', 'resolution_notes', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at'         => 'datetime',
            'cancelled_at'        => 'datetime',
            'verification_photos' => 'array',
            'dispatched_at'       => 'datetime',
            'received_at'         => 'datetime',
            'short_reported_at'   => 'datetime',
            'resolved_at'         => 'datetime',
        ];
    }

    /** Receipt-condition options. 'incomplete' = arrived without parts/accessories (short). */
    public const CONDITIONS = ['ok', 'damaged', 'missing', 'incomplete'];

    /** Short-received resolutions an admin can choose. */
    public const RESOLUTIONS = [
        'received_later'     => 'Received later',
        'lost'               => 'Mark lost (write off — needs approval)',
        'returned_to_sender' => 'Return to sender',
        'accepted_as_is'     => 'Accept as-is (incomplete)',
    ];

    public function isInTransit(): bool
    {
        return $this->receipt_status === 'in_transit';
    }

    public function isShort(): bool
    {
        return $this->receipt_status === 'short';
    }

    /** Awaiting the destination's receipt or an admin's short-received resolution. */
    public function isOpen(): bool
    {
        return in_array($this->receipt_status, ['in_transit', 'short'], true);
    }

    /** One word for lists/exports — combines approval state and physical receipt state. */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->cancelled_at) {
            return 'Cancelled';
        }

        return match (true) {
            $this->status === 'pending_approval' => 'Pending Approval',
            $this->status === 'rejected'         => 'Rejected',
            default => match ($this->receipt_status) {
                'in_transit' => 'In Transit',
                'short'      => 'Short Received',
                'received'   => $this->resolution === 'accepted_as_is' ? 'Received (incomplete)' : 'Received',
                'lost'       => 'Lost (written off)',
                'returned'   => 'Returned to Sender',
                default      => 'Completed',
            },
        };
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function shortReportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'short_reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function previousStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'previous_status_id');
    }

    /**
     * Company isolation: a movement is visible when its asset is, or when either side of it
     * is one of the actor's companies — so both seller and receiver see an inter-company
     * transfer even after the asset has changed hands.
     *
     * @param int[] $ids
     */
    protected function companyVisibilityConstraint(\Illuminate\Database\Eloquent\Builder $builder, array $ids): void
    {
        $table = $this->getTable();

        $builder->where(fn ($q) => $q
            ->whereHas('asset', fn ($aq) => $aq->withTrashed())
            ->orWhereIn("$table.from_company_id", $ids)
            ->orWhereIn("$table.to_company_id", $ids));
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MovementType::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AssetMovementBatch::class, 'batch_id');
    }

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function fromCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_custodian_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_custodian_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'to_status_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /** Forward-compat hook consumed by ApprovalRequest::getApprovableLabelAttribute(). */
    public function getApprovalLabel(): string
    {
        return ($this->movementType?->name ?? 'Movement') . ' — ' . (\App\Models\Asset::labelFor($this->asset));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('movement');
    }
}
