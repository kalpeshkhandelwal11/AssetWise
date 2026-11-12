<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preventive maintenance that repeats: when a record with repeat_every_months is completed,
 * the next one is scheduled automatically (MaintenanceService::scheduleNext()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('repeat_every_months')->nullable()->after('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn('repeat_every_months');
        });
    }
};
