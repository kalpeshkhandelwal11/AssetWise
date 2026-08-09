<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key-value store for admin-configurable toggles. Deliberately not
 * tag-specific — M05 is the first consumer (tag_code_type) but later modules
 * can reuse this table instead of each inventing a bespoke settings table.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
