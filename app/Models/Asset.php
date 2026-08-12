<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'asset_tag', 'name', 'description', 'serial_number', 'model', 'manufacturer',
        'company_id', 'category_id', 'asset_type_id', 'status_id',
        'location_id', 'building_id', 'floor_id', 'room_id',
        'custodian_id', 'department_id', 'branch_id',
        'purchase_date', 'purchase_cost', 'vendor', 'warranty_expiry', 'amc_expiry',
        'is_eol', 'eol_projected_date',
        'notes', 'is_active', 'created_by', 'updated_by',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        return $this->belongsTo(User::class, 'custodian_id');
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
        return $this->fieldValues()->exists();
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

    public function isDisposed(): bool
    {
        return $this->status?->code === 'DISPOSED';
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status?->code === 'MAINTENANCE';
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
