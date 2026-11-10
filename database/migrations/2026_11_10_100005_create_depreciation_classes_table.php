<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies Act 2013 Schedule II asset classes (useful life in years). Each asset category
 * maps to one class; the class supplies the default useful life for the category's
 * depreciation default and decides whether the category depreciates at all (Land doesn't).
 * Rows are seeded by ReferenceDataSeeder from the company's FY 26-27 register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->decimal('useful_life_years', 5, 2)->nullable();
            $table->boolean('is_depreciable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('asset_categories', function (Blueprint $table) {
            $table->foreignId('depreciation_class_id')->nullable()->after('parent_id')
                ->constrained('depreciation_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depreciation_class_id');
        });
        Schema::dropIfExists('depreciation_classes');
    }
};
