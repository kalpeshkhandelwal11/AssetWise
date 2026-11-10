<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Contract-end handover of a toll plaza. NHAI-owned assets are returned (no money); our own
 * are sold to a party, given to NHAI (written off — the default suggestion) or moved to another
 * plaza. Approvable of the `handover` workflow; applied per line on approval.
 */
class PlazaHandover extends Model
{
    use LogsActivity;

    public const ACTIONS = [
        'return_to_nhai' => 'Return to NHAI',
        'give_to_nhai'   => 'Give to NHAI (write off)',
        'sell'           => 'Sell to party',
        'move'           => 'Move to another plaza',
    ];

    protected $fillable = [
        'reference_no', 'location_id', 'company_id', 'authority_company_id', 'handover_date',
        'document_no', 'document_path', 'signatories', 'remarks', 'status', 'apply_error',
        'approval_request_id', 'requested_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'approved_at'   => 'datetime',
            'signatories'   => 'array',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlazaHandoverLine::class, 'handover_id')->orderBy('id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'authority_company_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function getApprovalLabel(): string
    {
        return sprintf('Plaza handover %s — %s (%d asset%s)', $this->reference_no, $this->location?->name ?? 'plaza',
            $this->lines()->count(), $this->lines()->count() === 1 ? '' : 's');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('handover');
    }
}
