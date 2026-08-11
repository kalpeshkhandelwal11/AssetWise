<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gain/loss on disposal: the existing disposal_value column is the sale proceeds;
        // these capture the net book value snapshot at write-off and the resulting gain/loss
        // (proceeds - NBV). Populated by DisposalService once M16 depreciation is stopped.
        Schema::table('disposal_requests', function (Blueprint $table) {
            $table->decimal('net_book_value_at_disposal', 14, 2)->nullable()->after('disposal_value');
            $table->decimal('gain_loss', 14, 2)->nullable()->after('net_book_value_at_disposal');
        });
    }

    public function down(): void
    {
        Schema::table('disposal_requests', function (Blueprint $table) {
            $table->dropColumn(['net_book_value_at_disposal', 'gain_loss']);
        });
    }
};
