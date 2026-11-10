<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company isolation: a user sees only the assets (and everything hanging off them) of the
 * companies they are assigned to, unless `all_companies` is set or they hold
 * `companies.view_all`. See App\Support\CompanyVisibility.
 *
 * `assets.operating_company_id` lets an externally owned asset (an NHAI asset at a plaza we
 * operate) be visible to the internal company that runs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('all_companies')->default(false)->after('is_active');
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('operating_company_id')->nullable()->after('company_id')
                ->constrained('companies')->nullOnDelete();
        });

        // Nobody loses access on deploy: every pre-existing account keeps seeing everything
        // until an admin narrows it on /admin/users.
        DB::table('users')->update(['all_companies' => true]);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operating_company_id');
        });
        Schema::dropIfExists('company_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('all_companies');
        });
    }
};
