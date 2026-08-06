<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_field_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->foreignId('category_field_id')->constrained('category_fields')->cascadeOnDelete();
            $table->enum('override_type', ['hide', 'relabel', 'change_required']);
            $table->string('override_label')->nullable();
            $table->boolean('is_required')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'category_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_field_overrides');
    }
};
