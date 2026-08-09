<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            // Every route in this app requires auth (no public routes exist anywhere else),
            // so an unauthenticated scan is not a case this schema needs to model.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('method', ['camera', 'manual']);
            $table->timestamp('scanned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
