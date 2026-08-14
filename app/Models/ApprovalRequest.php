<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'approvable_type', 'approvable_id',
        'status', 'current_step', 'current_step_started_at', 'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'current_step'            => 'integer',
            'current_step_started_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'request_id')->latest('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ApprovalAttachment::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function currentStepDefinition(): ?ApprovalStep
    {
        return $this->workflow?->steps->firstWhere('level', $this->current_step);
    }

    public function stepDefinition(int $level): ?ApprovalStep
    {
        return $this->workflow?->steps->firstWhere('level', $level);
    }

    /**
     * Next step by level order — not level+1, so a workflow whose levels have gaps
     * (1, 3, 7) still advances correctly. Returns null on the final step.
     */
    public function nextStepAfter(int $level): ?ApprovalStep
    {
        return $this->workflow?->steps
            ->where('level', '>', $level)
            ->sortBy('level')
            ->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Forward-compat hook: consumer models (M09 movements, M05 tag replacements, …) can
     * implement getApprovalLabel() for a nicer inbox display. Falls back to class + id.
     */
    public function getApprovableLabelAttribute(): string
    {
        $model = $this->approvable;

        if (! $model) {
            return class_basename($this->approvable_type) . ' #' . $this->approvable_id;
        }

        if (method_exists($model, 'getApprovalLabel')) {
            return $model->getApprovalLabel();
        }

        return class_basename($model) . ' #' . $model->getKey();
    }
}
