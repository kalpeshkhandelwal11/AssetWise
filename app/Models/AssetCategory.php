<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'depreciation_class_id', 'name', 'code', 'asset_prefix', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssetCategory::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class, 'category_id');
    }

    public function fieldOverrides(): HasMany
    {
        return $this->hasMany(CategoryFieldOverride::class, 'category_id');
    }

    public function depreciationClass(): BelongsTo
    {
        return $this->belongsTo(DepreciationClass::class);
    }

    /** The Schedule II class of this category, inherited from the nearest ancestor if unset. */
    public function effectiveDepreciationClass(): ?DepreciationClass
    {
        $cursor = $this;
        $guard = 0;

        while ($cursor && $guard++ < 20) {
            if ($cursor->depreciation_class_id) {
                return $cursor->depreciationClass;
            }
            $cursor = $cursor->parent;
        }

        return null;
    }

    public function depreciationDefault(): HasOne
    {
        return $this->hasOne(CategoryDepreciationDefault::class, 'category_id');
    }
}
