<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            // "Only one active assignment per asset" is enforced in TagService, not a DB
            // constraint — MySQL has no clean partial-unique-index story here.
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tag_assignments');
    }
};
