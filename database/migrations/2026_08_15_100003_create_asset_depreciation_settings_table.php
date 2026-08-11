<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciation_settings', function (Blueprint $table) {
            $table->id();

            // Deliberately NOT unique on asset_id: a capitalization or inter-company transfer
            // supersedes the current row (is_active=false + stopped_at + superseded_by) and
            // creates a fresh one, so an asset can accumulate a chain of settings over its life.
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('depreciation_method_id')->constrained('depreciation_methods')->restrictOnDelete();

            $table->unsignedInteger('useful_life_months');
            $table->decimal('salvage_value', 14, 2)->nullable();
            $table->decimal('salvage_percent', 7, 4)->nullable();

            $table->date('start_date');
            $table->decimal('cost_basis', 14, 2);

            // Cached bookmarks kept in sync by DepreciationService::postDuePeriods() so the
            // asset financial tab and reports don't re-sum the schedule on every read.
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->decimal('current_book_value', 14, 2);

            $table->boolean('is_active')->default(true);
            $table->date('stopped_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('asset_depreciation_settings')->nullOnDelete();

            $table->timestamps();

            $table->index(['asset_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_settings');
    }
};
