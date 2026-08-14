<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Cancelled" (requester/admin withdrew a pending request) is tracked with a nullable
     * timestamp rather than a new status enum value — enum ALTERs are MySQL-only and break the
     * SQLite test DB. A cancelled movement keeps status='rejected' but carries cancelled_at, and
     * the UI shows it as "Cancelled". Cross-DB safe.
     */
    public function up(): void
    {
        foreach (['asset_movements', 'asset_movement_batches'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('cancelled_at')->nullable()->after('status');
                $t->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['asset_movements', 'asset_movement_batches'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('cancelled_by');
                $t->dropColumn('cancelled_at');
            });
        }
    }
};
