<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_type_id')->constrained('movement_types')->restrictOnDelete();

            // Shared destination applied to every asset in the batch on approval.
            $table->foreignId('to_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();

            // Set once M17 creates a kit_assignments table; unconstrained so this table
            // doesn't have to wait on that module. AssetMovementBatch is the shared
            // grouping model for both this module's ad-hoc bulk moves (null here) and
            // M17's future kit assignments (set) — see MovementService::applyBulk().
            $table->unsignedBigInteger('kit_assignment_id')->nullable();

            $table->enum('status', ['pending_approval', 'rejected', 'completed'])->default('pending_approval');
            $table->text('notes')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movement_batches');
    }
};
