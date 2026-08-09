<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['tag_id', 'asset_id', 'user_id', 'method', 'scanned_at'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
