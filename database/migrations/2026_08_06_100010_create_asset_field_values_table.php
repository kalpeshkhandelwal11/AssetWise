<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('category_field_id')->constrained('category_fields')->restrictOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 15, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'category_field_id']);
            $table->index(['category_field_id', 'value_text']);
            $table->index(['category_field_id', 'value_number']);
            $table->index(['category_field_id', 'value_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_field_values');
    }
};
