<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_schedule_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            // Explicit short FK name — the auto-generated
            // "depreciation_schedule_lines_asset_depreciation_setting_id_foreign" is 65 chars,
            // over MySQL's 64-char identifier limit (SQLite in tests doesn't enforce it).
            $table->foreignId('asset_depreciation_setting_id')
                ->constrained('asset_depreciation_settings', indexName: 'dep_lines_setting_fk')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            // Companies Act daily proration: first/last periods are partial, so each line
            // records how many days of the useful life fell inside that calendar month.
            $table->unsignedSmallInteger('days_in_period');

            $table->decimal('opening_book_value', 14, 2);
            $table->decimal('depreciation_amount', 14, 2);
            $table->decimal('accumulated_depreciation', 14, 2);
            $table->decimal('closing_book_value', 14, 2);

            // scheduled -> posted (idempotent monthly command flips due lines to posted).
            $table->enum('status', ['scheduled', 'posted'])->default('scheduled');
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->unique(['asset_depreciation_setting_id', 'period_year', 'period_month'], 'dep_line_setting_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_schedule_lines');
    }
};
