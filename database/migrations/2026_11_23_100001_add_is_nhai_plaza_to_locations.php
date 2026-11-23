<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toll plazas that work with NHAI — only these appear on the Handover / Takeover dashboard.
 * Backfilled from existing data so the dashboard is not empty after deploy: every location that
 * already holds NHAI-owned assets, or had an NHAI takeover, is a toll plaza working with NHAI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_nhai_plaza')->default(false)->after('is_toll_plaza');
        });

        $nhaiCompanyIds = DB::table('companies')->where('party_type', 'external_authority')->pluck('id');

        $fromAssets = $nhaiCompanyIds->isEmpty()
            ? collect()
            : DB::table('assets')->whereIn('company_id', $nhaiCompanyIds)->whereNotNull('location_id')->distinct()->pluck('location_id');
        $fromTakeovers = DB::table('plaza_takeovers')->where('kind', 'nhai')->distinct()->pluck('location_id');

        $ids = $fromAssets->merge($fromTakeovers)->filter()->unique()->values();
        if ($ids->isNotEmpty()) {
            DB::table('locations')->whereIn('id', $ids)->update(['is_toll_plaza' => true, 'is_nhai_plaza' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('is_nhai_plaza');
        });
    }
};
