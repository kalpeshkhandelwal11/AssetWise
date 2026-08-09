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
    }
}
