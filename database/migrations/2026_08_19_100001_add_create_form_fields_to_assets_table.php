<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('vendor_invoice_no')->nullable()->after('vendor');
            $table->unsignedSmallInteger('useful_life_years')->nullable()->after('eol_projected_date');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['vendor_invoice_no', 'useful_life_years']);
        });
    }
};
