<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'field_key', 'label', 'field_type', 'is_required',
        'validation_rules', 'display_order', 'is_searchable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'is_required'      => 'boolean',
            'is_searchable'    => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CategoryFieldOption::class)->orderBy('sort_order');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(CategoryFieldOverride::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AssetFieldValue::class);
    }
}
