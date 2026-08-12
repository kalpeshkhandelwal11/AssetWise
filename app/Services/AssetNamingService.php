<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human asset code (`assets.asset_tag`) from an admin-configurable series —
 * PREFIX + '-' + zero-padded counter (e.g. AST-0001). Distinct from the M05 physical tag
 * pool. The counter is incremented under a row lock so concurrent creates never collide.
 */
class AssetNamingService
{
    public function enabled(): bool
    {
        return Setting::get('asset_naming_enabled', '1') === '1';
    }

    /** A non-consuming preview of the next code (for the form hint). */
    public function preview(): string
    {
        return $this->format((int) Setting::get('asset_naming_next', '1'));
    }

    /** Consume and return the next code, atomically advancing the counter. */
    public function next(): string
    {
        return DB::transaction(function () {
            // Ensure the row exists, then lock it for the read-modify-write.
            Setting::firstOrCreate(['key' => 'asset_naming_next'], ['value' => '1']);
            $row = Setting::where('key', 'asset_naming_next')->lockForUpdate()->first();

            $current = (int) $row->value;
            $row->update(['value' => (string) ($current + 1)]);

            return $this->format($current);
        });
    }

    private function format(int $number): string
    {
        $prefix = Setting::get('asset_naming_prefix', 'AST');
        $padding = (int) Setting::get('asset_naming_padding', '4');

        return $prefix . '-' . str_pad((string) $number, max($padding, 1), '0', STR_PAD_LEFT);
    }
}
