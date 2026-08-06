<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('field_key', 100);
            $table->string('label');
            $table->enum('field_type', ['text', 'number', 'date', 'dropdown', 'boolean', 'textarea']);
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_fields');
    }
};
