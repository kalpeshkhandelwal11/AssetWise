<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only cross-module status-change ledger (M09 now; M11/M13 write to it
        // later too) — deliberately not just a subset of asset_movements' own columns,
        // since not every status change originates from a movement.
        Schema::create('asset_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('asset_statuses')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_status_histories');
    }
};
