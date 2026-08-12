<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assignment/return header. In `single` approval mode this row is the approvable and
        // the actual moves hang off an AssetMovementBatch (batch.kit_assignment_id = this id);
        // in `per_asset` mode there is no batch and each asset_movements.kit_assignment_id
        // points here directly. kit_id null = ad-hoc bundle.
        Schema::create('kit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->nullable()->constrained('kits')->nullOnDelete();
            $table->enum('direction', ['out', 'return'])->default('out');
            $table->foreignId('parent_assignment_id')->nullable()->constrained('kit_assignments')->nullOnDelete();

            $table->foreignId('movement_type_id')->constrained('movement_types')->restrictOnDelete();
            $table->foreignId('to_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('to_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Snapshot of the kit_assignment_approval_mode setting at submit time.
            $table->enum('approval_mode', ['single', 'per_asset'])->default('single');
            $table->enum('status', ['pending_approval', 'completed', 'rejected'])->default('pending_approval');

            // Set only in single mode (the kit_assignment itself is the approvable).
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_assignments');
    }
};
