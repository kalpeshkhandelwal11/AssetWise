<?php

namespace App\Models;

use App\Models\Scopes\CompanyVisibilityScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ScopedBy([CompanyVisibilityScope::class])]
class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'asset_tag', 'name', 'description', 'serial_number', 'model', 'manufacturer',
        'company_id', 'operating_company_id', 'category_id', 'asset_type_id', 'status_id',
        'location_id', 'building_id', 'floor_id', 'room_id',
        'custodian_id', 'department_id', 'branch_id',
        'purchase_date', 'purchase_cost', 'vendor', 'vendor_invoice_no', 'warranty_expiry', 'amc_expiry',
        'is_eol', 'eol_projected_date', 'useful_life_years',
        'notes', 'is_active', 'created_by', 'updated_by',
        'condition', 'condition_remarks', 'takeover_line_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date'       => 'date',
            'warranty_expiry'     => 'date',
            'amc_expiry'          => 'date',
            'eol_projected_date'  => 'date',
            'purchase_cost'       => 'decimal:2',
            'is_active'           => 'boolean',
            'is_eol'              => 'boolean',
        ];
    }

    /**
     * Display name for a possibly-missing asset relation (deleted, or hidden by company
     * isolation) — replaces the old "Asset #12" fallbacks, which leaked raw ids into the UI.
     */
    public static function labelFor(?self $asset): string
    {
        return $asset?->name ?? 'Asset (unavailable)';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Internal company operating an externally owned asset (e.g. an NHAI asset at our plaza). */
    public function operatingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'operating_company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'status_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'custodian_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssetPhoto::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(AssetFieldValue::class);
    }

    public function hasCustomFieldData(): bool
    {
        // An unticked Yes/No field is posted as 0 on every save, so a bare "No" is not data
        // someone entered — counting it would lock the category of every asset in a category
        // that merely has a boolean field.
        return $this->fieldValues()
            ->where(fn ($q) => $q->whereNotNull('value_text')
                ->orWhereNotNull('value_number')
                ->orWhereNotNull('value_date')
                ->orWhere('value_boolean', true))
            ->exists();
    }

    public function tagAssignments(): HasMany
    {
        return $this->hasMany(AssetTagAssignment::class)->latest('assigned_at');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('created_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class)->latest('created_at');
    }

    public function disposalRequests(): HasMany
    {
        return $this->hasMany(DisposalRequest::class)->latest('created_at');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class)->latest('created_at');
    }

    public function amcContracts(): HasMany
    {
        return $this->hasMany(AmcContract::class)->latest('end_date');
    }

    public function warrantyRecords(): HasMany
    {
        return $this->hasMany(WarrantyRecord::class)->latest('end_date');
    }

    /** Full history of depreciation settings — a new active row supersedes the old on transfer/capitalization. */
    public function depreciationSettings(): HasMany
    {
        return $this->hasMany(AssetDepreciationSetting::class)->latest('id');
    }

    public function depreciationScheduleLines(): HasMany
    {
        return $this->hasMany(DepreciationScheduleLine::class);
    }

    public function depreciationRequests(): HasMany
    {
        return $this->hasMany(DepreciationSettingRequest::class)->latest('id');
    }

    /** The single active depreciation setting driving the current schedule, if any. */
    public function activeDepreciationSetting(): ?AssetDepreciationSetting
    {
        return $this->depreciationSettings()->where('is_active', true)->first();
    }

    public function hasPendingDepreciationRequest(): bool
    {
        return $this->depreciationRequests()->where('status', 'pending_approval')->exists();
    }

    /** Kit-template slots this asset is linked to (M17) — drives the asset's "Kits" tab. */
    public function kitAssets(): HasMany
    {
        return $this->hasMany(KitAsset::class);
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function isDisposed(): bool
    {
        return $this->status?->code === 'DISPOSED';
    }

    /** Left the active register: scrapped, returned to NHAI, handed over, sold externally. */
    public function isRetired(): bool
    {
        return in_array($this->status?->code, AssetStatus::TERMINAL, true);
    }

    /** Owned by an external party (NHAI) — never priced, depreciated, sold or disposed by us. */
    public function isExternallyOwned(): bool
    {
        return (bool) $this->company?->isExternal();
    }

    /** Line of an NHAI/purchase takeover this asset was created from. */
    public function takeoverLine(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PlazaTakeoverLine::class);
    }

    /** In a handover still awaiting approval — locked like a pending movement. */
    public function hasPendingHandover(): bool
    {
        return PlazaHandoverLine::where('asset_id', $this->id)
            ->whereHas('handover', fn ($q) => $q->where('status', 'pending_approval'))
            ->exists();
    }

    /** Approved for a physical move but not yet received at the destination. */
    public function isInTransit(): bool
    {
        return $this->status?->code === 'IN_TRANSIT';
    }

    /** The movement currently awaiting receipt (or a short-received resolution), if any. */
    public function openTransitMovement(): ?AssetMovement
    {
        return $this->movements()->whereIn('receipt_status', ['in_transit', 'short'])->first();
    }

    public function hasPendingLoss(): bool
    {
        return AssetLossRequest::where('asset_id', $this->id)->where('status', 'pending_approval')->exists();
    }

    /**
     * Assets that can be acted on (picked for a movement, kit, disposal, maintenance): not a
     * draft, not in transit, not retired.
     */
    public function scopeOperable(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereDoesntHave('status', fn ($q) => $q->whereIn('code', ['DRAFT', 'IN_TRANSIT', ...AssetStatus::TERMINAL]));
    }

    /** In the creation-approval holding state (M-create): saved but not yet approved live. */
    public function isDraft(): bool
    {
        return $this->status?->code === 'DRAFT';
    }

    public function hasPendingCreationApproval(): bool
    {
        $own = $this->approvalRequests()
            ->where('status', 'pending')
            ->whereHas('workflow', fn ($q) => $q->where('module', 'asset_creation'))
            ->exists();

        // A bulk-imported draft is covered by its batch's single approval request — opening a
        // second, per-asset request would let it be approved twice.
        return $own || ImportBatchRow::where('asset_id', $this->id)
            ->whereHas('batch', fn ($q) => $q->where('approval_status', 'pending'))
            ->exists();
    }

    /** Consumed by ApprovalRequest::getApprovableLabelAttribute() for the approver inbox. */
    public function getApprovalLabel(): string
    {
        return 'New asset — ' . $this->name;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status?->code === 'MAINTENANCE';
    }

    /**
     * The movement that handed this asset to its current custodian, if that person has not
     * acknowledged it yet. Null once acknowledged, or when custody has since moved on.
     */
    public function pendingAcknowledgement(): ?AssetMovement
    {
        if (! $this->custodian_id) {
            return null;
        }

        $latest = $this->movements()->where('to_custodian_id', $this->custodian_id)->where('status', 'completed')->first();

        return $latest && $latest->awaitsAcknowledgement() ? $latest : null;
    }

    public function hasPendingMovement(): bool
    {
        return $this->movements()->where('status', 'pending_approval')->exists();
    }

    public function hasPendingDisposal(): bool
    {
        return $this->disposalRequests()->whereIn('status', ['pending_approval', 'approved', 'written_off'])->exists();
    }

    /** The Tag behind this asset's current active AssetTagAssignment, if any. */
    public function activeTag(): ?Tag
    {
        return $this->tagAssignments()->where('status', 'active')->first()?->tag;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('asset');
    }
}
