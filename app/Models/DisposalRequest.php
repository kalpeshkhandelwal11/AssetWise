<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DisposalRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_id', 'disposal_type_id', 'reason', 'status',
        'approval_request_id', 'requested_by',
        'disposal_value', 'written_off_at', 'written_off_by',
        'scrapped_at', 'scrapped_by',
    ];

    protected function casts(): array
    {
        return [
            'disposal_value' => 'decimal:2',
            'written_off_at' => 'datetime',
            'scrapped_at'    => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function disposalType(): BelongsTo
    {
        return $this->belongsTo(DisposalType::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function writtenOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_off_by');
    }

    public function scrappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scrapped_by');
    }

    /** Forward-compat hook consumed by ApprovalRequest::getApprovableLabelAttribute(). */
    public function getApprovalLabel(): string
    {
        return 'Disposal — ' . ($this->asset?->name ?? 'Asset #' . $this->asset_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('disposal');
    }
}
