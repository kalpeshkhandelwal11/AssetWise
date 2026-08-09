<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagReplacementRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'current_tag_id', 'new_tag_id', 'reason', 'status', 'approval_request_id', 'requested_by',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function currentTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'current_tag_id');
    }

    public function newTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'new_tag_id');
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
        return 'Tag Replacement — ' . ($this->asset?->name ?? 'Asset #' . $this->asset_id);
    }
}
