<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-category asset-ID naming: each category carries its own prefix and its own
     * independent counter (AssetNamingService). A null/blank prefix falls back to the
     * global default series (Setting `asset_naming_prefix` + `asset_naming_next`).
     */
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->string('asset_prefix', 20)->nullable()->after('code');
            $table->unsignedInteger('asset_seq_next')->default(1)->after('asset_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn(['asset_prefix', 'asset_seq_next']);
        });
    }
};
