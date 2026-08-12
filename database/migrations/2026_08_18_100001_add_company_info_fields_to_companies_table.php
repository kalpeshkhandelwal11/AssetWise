<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Hierarchy
            $table->foreignId('parent_company_id')->nullable()->after('code')
                ->constrained('companies')->nullOnDelete();
            $table->boolean('is_head_office')->default(false)->after('parent_company_id');

            // Legal & tax
            $table->string('legal_name')->nullable()->after('name');
            $table->string('gstin', 15)->nullable()->after('is_head_office');
            $table->string('pan', 10)->nullable()->after('gstin');
            $table->string('cin', 21)->nullable()->after('pan');

            // Registered (statutory) address — distinct from the operating address
            $table->text('registered_address')->nullable()->after('cin');
            $table->string('registered_city', 100)->nullable()->after('registered_address');
            $table->string('registered_state', 100)->nullable()->after('registered_city');
            $table->string('registered_pincode', 12)->nullable()->after('registered_state');
            $table->string('registered_country', 100)->nullable()->after('registered_pincode');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_company_id');
            $table->dropColumn([
                'is_head_office', 'legal_name', 'gstin', 'pan', 'cin',
                'registered_address', 'registered_city', 'registered_state',
                'registered_pincode', 'registered_country',
            ]);
        });
    }
};
