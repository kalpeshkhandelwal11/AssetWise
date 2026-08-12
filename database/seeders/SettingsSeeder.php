<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Default values for the generic `settings` table (M05 introduces it). Install-only,
 * like RolePermissionSeeder — firstOrCreate so re-running never clobbers an admin's edit.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'tag_code_type'], ['value' => 'qr']);

        // M16: Companies Act Schedule II residual convention — used when a category/asset
        // sets neither a fixed salvage value nor a salvage percent.
        Setting::firstOrCreate(['key' => 'depreciation_default_salvage_percent'], ['value' => '5']);

        // M17: kit assignment approval granularity — 'single' (one approval per kit) or
        // 'per_asset' (one approval per asset in the kit).
        Setting::firstOrCreate(['key' => 'kit_assignment_approval_mode'], ['value' => 'single']);
    }
}
