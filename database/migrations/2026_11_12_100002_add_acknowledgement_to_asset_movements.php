<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The person an asset is assigned to confirms they actually have it. Until then the movement
 * shows as "awaiting acknowledgement" (AssetMovement::awaitsAcknowledgement()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn('acknowledged_at');
        });
    }
};
