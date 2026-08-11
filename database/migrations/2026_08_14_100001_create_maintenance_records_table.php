<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('maintenance_type_id')->constrained('maintenance_types')->restrictOnDelete();

            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');

            $table->date('scheduled_date')->nullable();
            $table->date('performed_date')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('description')->nullable();

            // Captured when status flips to in_progress so the asset's prior status_id
            // can be restored on completion/cancellation — mirrors asset_status_histories'
            // from/to shape but scoped to this one record instead of a global ledger row.
            $table->foreignId('previous_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();

            $table->foreignId('logged_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
