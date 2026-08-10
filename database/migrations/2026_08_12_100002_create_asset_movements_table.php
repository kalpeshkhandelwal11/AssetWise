<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('movement_type_id')->constrained('movement_types')->restrictOnDelete();

            // Set when this row was created as part of a bulk (multi-select) submission —
            // see AssetMovementBatch. Null for a standalone single-asset movement.
            $table->foreignId('batch_id')->nullable()->constrained('asset_movement_batches')->nullOnDelete();
            // Denormalized copy of the owning batch's kit_assignment_id (once set by M17),
            // so "every movement for kit X" doesn't require a join through the batch table.
            $table->unsignedBigInteger('kit_assignment_id')->nullable();

            $table->foreignId('from_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('to_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('from_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();

            // No separate "approved" resting state: apply() runs synchronously inside the
            // ApprovalRequestApproved listener, so a row moves straight from
            // pending_approval to completed the moment the terminal step clears.
            $table->enum('status', ['pending_approval', 'rejected', 'completed'])->default('pending_approval');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
