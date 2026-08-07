<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->enum('approver_type', ['role', 'user']);
            // Spatie role *name*, not an FK — roles are looked up by name everywhere else in this app.
            $table->string('approver_role')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('escalation_hours')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
