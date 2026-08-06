<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAttachment extends Model
{
    protected $fillable = ['asset_id', 'type', 'path', 'original_name', 'mime', 'size'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
