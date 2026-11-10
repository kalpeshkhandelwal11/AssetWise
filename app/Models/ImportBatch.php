<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'category_id', 'user_id', 'filename', 'status',
        'total_rows', 'success_count', 'error_count',
        'approval_request_id', 'approval_status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class, 'batch_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /** Inbox label when a bulk import is the approvable of an asset_creation request. */
    public function getApprovalLabel(): string
    {
        return sprintf('Bulk import — %d asset(s) · %s', (int) $this->success_count, $this->category?->name ?? 'Category');
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }
}
