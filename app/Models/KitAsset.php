<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'kit_item_id', 'asset_id',
    ];

    public function kitItem(): BelongsTo
    {
        return $this->belongsTo(KitItem::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
