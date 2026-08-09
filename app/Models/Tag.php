<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag_number', 'batch_id', 'code_type', 'qr_payload', 'barcode_value', 'status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TagBatch::class, 'batch_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetTagAssignment::class, 'tag_id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetTagAssignment::class, 'tag_id')->where('status', 'active');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'tag_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
