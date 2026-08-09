<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTagAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'tag_id', 'status', 'assigned_by', 'assigned_at', 'deactivated_by', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'    => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }
}
