<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_depreciation_defaults', function (Blueprint $table) {
            $table->id();

            // One default row per category — DepreciationService::resolveSettings() falls back
            // to this when an asset has no per-asset override.
            $table->foreignId('category_id')->unique()->constrained('asset_categories')->cascadeOnDelete();
            $table->foreignId('depreciation_method_id')->constrained('depreciation_methods')->restrictOnDelete();

            $table->unsignedInteger('useful_life_months');

            // Salvage is "fixed OR percent" — a fixed value wins over a percent when both set;
            // both null falls back to the depreciation_default_salvage_percent setting (5%).
            $table->decimal('salvage_value', 14, 2)->nullable();
            $table->decimal('salvage_percent', 7, 4)->nullable();

            $table->enum('start_basis', ['purchase_date', 'commission_date'])->default('purchase_date');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_depreciation_defaults');
    }
};
