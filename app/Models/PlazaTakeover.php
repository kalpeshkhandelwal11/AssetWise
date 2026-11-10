<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A toll-plaza takeover. kind = nhai: NHAI hands over its assets (no money, NHAI-owned,
 * never depreciated). kind = purchase: we buy assets from the previous agency or another
 * party (priced, ours, depreciated). Approvable of the `takeover` workflow.
 */
class PlazaTakeover extends Model
{
    use LogsActivity;

    public const KINDS = [
        'nhai'     => 'Takeover from NHAI',
        'purchase' => 'Purchase from party',
    ];

    /** Signature blocks on the certificate (from the NHAI checklist). */
    public const SIGNATORIES = [
        'concessionaire'       => 'Concessionaire',
        'system_integrator'    => 'System Integrator',
        'previous_agency'      => 'Toll Agency (Previous)',
        'present_agency'       => 'Toll Agency (Present)',
        'independent_engineer' => 'Independent Engineer',
        'nhai_piu'             => 'NHAI PIU (Site Engineer)',
    ];

    protected $fillable = [
        'reference_no', 'kind', 'location_id', 'from_company_id', 'company_id', 'custodian_id',
        'takeover_date', 'document_no', 'document_path', 'signatories', 'remarks',
        'status', 'approval_request_id', 'requested_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'takeover_date' => 'date',
            'approved_at'   => 'datetime',
            'signatories'   => 'array',
        ];
    }

    public function isNhai(): bool
    {
        return $this->kind === 'nhai';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlazaTakeoverLine::class, 'takeover_id')->orderBy('line_no')->orderBy('id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'custodian_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /** Assets created from this takeover's lines. */
    public function assets()
    {
        return Asset::withoutGlobalScopes()->whereIn('takeover_line_id', $this->lines()->select('id'));
    }

    public function totalUnits(): int
    {
        return (int) $this->lines->sum('quantity');
    }

    public function totalValue(): float
    {
        return (float) $this->lines->sum(fn ($l) => (float) $l->unit_price * (int) $l->quantity);
    }

    public function getApprovalLabel(): string
    {
        return sprintf('%s %s — %s (%d asset%s)', self::KINDS[$this->kind] ?? 'Takeover', $this->reference_no,
            $this->location?->name ?? 'plaza', $this->totalUnits(), $this->totalUnits() === 1 ? '' : 's');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('takeover');
    }
}
