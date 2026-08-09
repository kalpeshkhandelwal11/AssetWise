<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            // Nullable only so generateBatch() can insert the row first and derive the
            // number from the row's own auto-increment id in the same transaction — every
            // committed row ends up with a non-null number in practice. Global, sequential,
            // never reused (tags are only ever retired to 'inactive', never deleted).
            $table->string('tag_number', 20)->nullable()->unique();
            $table->foreignId('batch_id')->constrained('tag_batches')->restrictOnDelete();
            $table->enum('code_type', ['qr', 'barcode']);
            $table->string('qr_payload')->nullable();
            $table->string('barcode_value')->nullable();
            $table->enum('status', ['available', 'assigned', 'inactive'])->default('available');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
