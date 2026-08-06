<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_field_id')->constrained('category_fields')->cascadeOnDelete();
            $table->string('option_value');
            $table->string('option_label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unique(['category_field_id', 'option_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_field_options');
    }
};
