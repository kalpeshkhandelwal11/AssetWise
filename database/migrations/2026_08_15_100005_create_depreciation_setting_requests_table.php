<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval-staging table (mirrors disposal_requests): the proposed depreciation
        // settings live here while the 'depreciation' workflow runs, and are written into a
        // new asset_depreciation_settings row only once ApprovalRequestApproved fires.
        Schema::create('depreciation_setting_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('depreciation_method_id')->constrained('depreciation_methods')->restrictOnDelete();

            $table->unsignedInteger('useful_life_months');
            $table->decimal('salvage_value', 14, 2)->nullable();
            $table->decimal('salvage_percent', 7, 4)->nullable();
            $table->date('start_date');
            $table->decimal('cost_basis', 14, 2);

            // initial   — first time an asset opts into depreciation
            // revision  — manual change to method/life/salvage
            // capitalization — raised by MaintenanceService when a record is capitalized
            $table->enum('change_type', ['initial', 'revision', 'capitalization'])->default('revision');
            $table->foreignId('source_maintenance_id')->nullable()->constrained('maintenance_records')->nullOnDelete();

            $table->enum('status', ['pending_approval', 'approved', 'applied', 'rejected'])->default('pending_approval');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_setting_requests');
    }
};
