<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('audit_campaigns')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();

            $table->enum('status', ['pending', 'verified', 'missing', 'damaged'])->default('pending');

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();

            // Snapshot of the asset's location/custodian at activation time so the auditor
            // sees where the register expected it, and the report still shows that even
            // after the asset later moves — mirrors maintenance_records.previous_status_id's
            // "capture now, read later" pattern.
            $table->foreignId('expected_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('expected_custodian_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['campaign_id', 'asset_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_items');
    }
};
