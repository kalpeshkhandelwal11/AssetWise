<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('disposal_type_id')->constrained('disposal_types')->restrictOnDelete();
            $table->text('reason');

            // No 'draft': the form submits straight to pending_approval. No 'approved'
            // resting-then-auto-applied step either — approval only unlocks write-off,
            // which is its own manual action, then scrap completes the lifecycle.
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'written_off', 'scrapped'])
                  ->default('pending_approval');

            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            $table->decimal('disposal_value', 12, 2)->nullable();
            $table->timestamp('written_off_at')->nullable();
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scrapped_at')->nullable();
            $table->foreignId('scrapped_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_requests');
    }
};
