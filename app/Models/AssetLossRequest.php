<?php

namespace App\Models;

use App\Models\Concerns\ScopedByAssetCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Approvable for the `asset_loss` workflow: declaring an asset lost/stolen (from a LOST/STOLEN
 * movement or a short-received resolution). On approval the asset is marked "Lost (Written
 * Off)", a WRITE_OFF disposal records the loss (−NBV) and depreciation stops.
 */
class AssetLossRequest extends Model
{
    use LogsActivity, ScopedByAssetCompany;

    protected $fillable = [
        'asset_id', 'movement_id', 'reason', 'notes', 'status', 'previous_status_id',
        'disposal_request_id', 'approval_request_id', 'requested_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(AssetMovement::class);
    }

    public function disposalRequest(): BelongsTo
    {
        return $this->belongsTo(DisposalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function getApprovalLabel(): string
    {
        return ($this->reason === 'stolen' ? 'Stolen' : 'Lost').' — write off '.Asset::labelFor($this->asset);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('asset_loss');
    }
}
