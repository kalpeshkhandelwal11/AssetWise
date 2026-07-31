<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_enabled')->default(false)->after('password');
            $table->boolean('must_change_password')->default(false)->after('mfa_enabled');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->boolean('is_active')->default(true)->after('password_changed_at');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled', 'must_change_password', 'password_changed_at', 'is_active', 'last_login_at']);
        });
    }
};
