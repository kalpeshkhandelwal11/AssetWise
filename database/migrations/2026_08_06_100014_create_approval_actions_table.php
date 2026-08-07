<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit log — same shape as login_histories: created_at *is* the act
        // timestamp, no updated_at, rows are never modified or deleted.
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_level');
            // null = system-generated (escalation)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['approve', 'reject', 'escalate']);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'step_level', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
