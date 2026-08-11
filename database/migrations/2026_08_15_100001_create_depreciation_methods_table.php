<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');

            // Strategy-pattern binding: the calculator class instantiated for this method.
            // New methods activate by flipping is_active + pointing at a class — no migration.
            $table->string('calculator_class');

            // MVP seeds only straight_line active; the rest are visible-but-not-selectable.
            $table->boolean('is_active')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_methods');
    }
};
