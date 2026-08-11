<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Multiple warranty records per asset are supported deliberately (e.g. a
        // manufacturer warranty plus a later extended-warranty purchase) — assets.warranty_expiry
        // stays a single quick-alert column synced to the furthest end_date across all of them.
        Schema::create('warranty_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('provider');
            $table->date('start_date')->nullable();
            $table->date('end_date');
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_records');
    }
};
