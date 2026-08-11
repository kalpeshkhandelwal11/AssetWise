<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaintenanceRecord extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_id', 'maintenance_type_id', 'status',
        'scheduled_date', 'performed_date', 'vendor', 'cost', 'description',
        'previous_status_id', 'logged_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'performed_date' => 'date',
            'cost'           => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function previousStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'previous_status_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('maintenance');
    }
}
