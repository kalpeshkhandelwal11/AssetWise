<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.2 — "Must be unique" custom fields + an index for the (app-enforced) unique serial number.
 *       A DB unique index on serial_number is added later by `assets:serial-duplicates
 *       --enforce`, only once live data is clean.
 * 1.5 — bulk imports go through the asset_creation approval as ONE request per batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            $table->boolean('is_unique')->default(false)->after('is_searchable');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('serial_number');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('approval_status', 20)->nullable(); // pending|approved|rejected; null = live import
        });

        Schema::table('import_batch_rows', function (Blueprint $table) {
            // The status the row asked for; applied when the batch's creation approval passes.
            $table->foreignId('intended_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batch_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intended_status_id');
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_request_id');
            $table->dropColumn('approval_status');
        });
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['serial_number']);
        });
        Schema::table('category_fields', function (Blueprint $table) {
            $table->dropColumn('is_unique');
        });
    }
};
