<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Physical assets currently linked to a template slot. Together with kit_items.quantity
        // this answers "is the kit ready?" (every slot's quantity filled).
        Schema::create('kit_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_item_id')->constrained('kit_items')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kit_item_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_assets');
    }
};
