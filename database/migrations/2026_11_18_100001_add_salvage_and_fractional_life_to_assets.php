<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The asset form now carries the depreciation inputs it shows: useful life (in years, halves
 * allowed — category lives such as 18 months or 7.5 years must fit) and salvage %. Both are
 * prefilled from the category's depreciation default and are what the asset's first schedule
 * uses (DepreciationService::resolveDefaults()), so the EOL date and depreciation agree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('useful_life_years', 5, 2)->nullable()->change();
            $table->decimal('salvage_percent', 5, 2)->nullable()->after('useful_life_years');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('salvage_percent');
            $table->unsignedSmallInteger('useful_life_years')->nullable()->change();
        });
    }
};
