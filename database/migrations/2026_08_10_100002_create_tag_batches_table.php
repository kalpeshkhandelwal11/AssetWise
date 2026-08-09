<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('quantity');
            // Snapshot of settings.tag_code_type at generation time — flipping the global
            // setting later must not retroactively relabel an already-printed batch.
            $table->enum('code_type', ['qr', 'barcode']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_batches');
    }
};
