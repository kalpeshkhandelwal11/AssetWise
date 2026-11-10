<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salvage became mandatory. Category defaults saved with neither a fixed value nor a percent
 * relied on the implicit 5% fallback — make that explicit so every default is valid under
 * the new rule (and so the form shows what is actually applied).
 */
return new class extends Migration
{
    public function up(): void
    {
        $percent = (float) (DB::table('settings')->where('key', 'depreciation_default_salvage_percent')->value('value') ?? 5);

        DB::table('category_depreciation_defaults')
            ->whereNull('salvage_value')
            ->whereNull('salvage_percent')
            ->update(['salvage_percent' => $percent]);
    }

    public function down(): void
    {
        // Not reversible — the explicit percent equals the old implicit behaviour.
    }
};
