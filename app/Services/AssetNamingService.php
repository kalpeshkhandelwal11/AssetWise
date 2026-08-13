<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human asset code (`assets.asset_tag`) as PREFIX + '-' + zero-padded counter
 * (e.g. LAP-0001). Distinct from the M05 physical tag pool.
 *
 * The series is **category-wise**: a category with a non-blank `asset_prefix` owns an
 * independent counter (`asset_categories.asset_seq_next`). A category without a prefix falls
 * back to the global default series (Setting `asset_naming_prefix` + `asset_naming_next`).
 * Padding and the on/off toggle stay global. Counters are advanced under a row lock so
 * concurrent creates never collide.
 */
class AssetNamingService
{
    public function enabled(): bool
    {
        return Setting::get('asset_naming_enabled', '1') === '1';
    }

    /** A non-consuming preview of the next code for a category (or the global default). */
    public function preview(?AssetCategory $category = null): string
    {
        if ($category && filled($category->asset_prefix)) {
            return $this->format($category->asset_prefix, (int) $category->asset_seq_next);
        }

        return $this->format(
            Setting::get('asset_naming_prefix', 'AST'),
            (int) Setting::get('asset_naming_next', '1'),
        );
    }

    /** Consume and return the next code, atomically advancing the relevant counter. */
    public function next(?AssetCategory $category = null): string
    {
        return DB::transaction(function () use ($category) {
            if ($category && filled($category->asset_prefix)) {
                return $this->nextForCategory($category);
            }

            return $this->nextGlobal();
        });
    }

    /** Per-category series — lock the category row and advance its own counter. */
    private function nextForCategory(AssetCategory $category): string
    {
        $row = AssetCategory::whereKey($category->id)->lockForUpdate()->first();
        $prefix = $row->asset_prefix;

        do {
            $current = (int) $row->asset_seq_next;
            // Direct assignment (not update([...])) — asset_seq_next is intentionally not
            // fillable, so mass assignment would silently drop it and never advance the counter.
            $row->asset_seq_next = $current + 1;
            $row->save();
            $code = $this->format($prefix, $current);
        } while (Asset::withTrashed()->where('asset_tag', $code)->exists());

        return $code;
    }

    /** Global default series — used when the category has no prefix. */
    private function nextGlobal(): string
    {
        Setting::firstOrCreate(['key' => 'asset_naming_next'], ['value' => '1']);
        $row = Setting::where('key', 'asset_naming_next')->lockForUpdate()->first();
        $prefix = Setting::get('asset_naming_prefix', 'AST');

        do {
            $current = (int) $row->value;
            $row->update(['value' => (string) ($current + 1)]);
            $code = $this->format($prefix, $current);
        } while (Asset::withTrashed()->where('asset_tag', $code)->exists());

        return $code;
    }

    private function format(string $prefix, int $number): string
    {
        $padding = (int) Setting::get('asset_naming_padding', '4');

        return $prefix . '-' . str_pad((string) $number, max($padding, 1), '0', STR_PAD_LEFT);
    }
}
