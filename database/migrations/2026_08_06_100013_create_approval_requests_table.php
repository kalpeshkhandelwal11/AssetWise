<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->morphs('approvable');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedTinyInteger('current_step');
            // Drives escalation timing: set on create and reset on every step advance.
            $table->timestamp('current_step_started_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'current_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
