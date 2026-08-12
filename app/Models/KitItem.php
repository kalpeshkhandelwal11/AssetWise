<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kit_id', 'label', 'category_id', 'quantity', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function kitAssets(): HasMany
    {
        return $this->hasMany(KitAsset::class);
    }

    /** Physical assets currently filling this slot. */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'kit_assets', 'kit_item_id', 'asset_id')->withTimestamps();
    }
}
