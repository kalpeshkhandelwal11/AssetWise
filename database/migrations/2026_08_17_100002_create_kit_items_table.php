<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Template slots. category_id optionally constrains which category may fill the slot;
        // quantity is how many assets the slot needs (drives the readiness indicator).
        Schema::create('kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained('kits')->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_items');
    }
};
