<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A supporting document uploaded when an approval request is submitted (see M09 movements).
 * Files live on the public disk under approvals/{approval_request_id}/.
 */
class ApprovalAttachment extends Model
{
    protected $fillable = [
        'approval_request_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
