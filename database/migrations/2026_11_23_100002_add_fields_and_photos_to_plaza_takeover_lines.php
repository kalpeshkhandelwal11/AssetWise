<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A takeover line carries the category's custom attributes and photos, like Add Asset does.
 * Both are applied to every asset the line expands into on submit (TakeoverService::expandLine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plaza_takeover_lines', function (Blueprint $table) {
            $table->json('field_values')->nullable()->after('remarks'); // field_key => value
            $table->json('photo_paths')->nullable()->after('field_values'); // public-disk paths
        });
    }

    public function down(): void
    {
        Schema::table('plaza_takeover_lines', function (Blueprint $table) {
            $table->dropColumn(['field_values', 'photo_paths']);
        });
    }
};
