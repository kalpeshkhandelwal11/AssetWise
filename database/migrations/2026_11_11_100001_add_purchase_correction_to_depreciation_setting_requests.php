<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * purchase_correction — a purchase cost / date edited on the asset form while depreciation is
 * running. Held for the depreciation approval; on approval both the schedule and the asset's
 * purchase fields change together (DepreciationService::applySettingChange()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depreciation_setting_requests', function (Blueprint $table) {
            $table->enum('change_type', ['initial', 'revision', 'capitalization', 'purchase_correction'])
                ->default('revision')->change();
        });
    }

    public function down(): void
    {
        Schema::table('depreciation_setting_requests', function (Blueprint $table) {
            $table->enum('change_type', ['initial', 'revision', 'capitalization'])
                ->default('revision')->change();
        });
    }
};
