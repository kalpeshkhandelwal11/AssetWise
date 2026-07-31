<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'code']);
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['building_id', 'code']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['floor_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('locations');
    }
};
