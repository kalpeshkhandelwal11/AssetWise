<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional capitalization of major maintenance (M11 -> M16): when flagged, the
        // capitalized amount is added to the depreciation cost basis and the useful life is
        // extended, and MaintenanceService raises a 'depreciation' approval to apply it.
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->boolean('is_capitalized')->default(false)->after('cost');
            $table->decimal('capitalized_amount', 14, 2)->nullable()->after('is_capitalized');
            $table->unsignedInteger('additional_useful_life_months')->nullable()->after('capitalized_amount');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['is_capitalized', 'capitalized_amount', 'additional_useful_life_months']);
        });
    }
};
