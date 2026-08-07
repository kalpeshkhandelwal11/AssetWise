<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'level', 'approver_type',
        'approver_role', 'approver_user_id', 'escalation_hours',
    ];

    protected function casts(): array
    {
        return [
            'level'            => 'integer',
            'escalation_hours' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /** Human-readable "who approves this step", for inbox rows and the admin list. */
    public function getApproverLabelAttribute(): string
    {
        return $this->approver_type === 'role'
            ? 'Role: ' . $this->approver_role
            : 'User: ' . ($this->approverUser?->name ?? '#' . $this->approver_user_id);
    }
}
