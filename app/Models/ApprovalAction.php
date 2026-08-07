<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Mirrors LoginHistory: created_at is the act timestamp, no updated_at.
 */
class ApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'step_level',
        'user_id',
        'action',
        'comment',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'step_level' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
