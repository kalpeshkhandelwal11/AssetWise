<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipt sign-off details captured at movement.verify (M09): the receiver records the
     * condition on arrival (ok / damaged / missing), free-text notes, and any photos. These
     * are record-only — they never mutate the asset's status (mirrors M10 audit findings).
     */
    public function up(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->string('verification_condition')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verification_condition');
            $table->json('verification_photos')->nullable()->after('verification_notes');
        });
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropColumn(['verification_condition', 'verification_notes', 'verification_photos']);
        });
    }
};
