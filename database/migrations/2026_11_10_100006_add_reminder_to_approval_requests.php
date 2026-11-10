<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 24h approval reminder (approvals:remind, hourly). Sent ONCE per request — the marker lives
 * here, not on approval_actions (whose `action` is an enum we must not ALTER under SQLite).
 * reminder_sent_step records the step it fired on, so switching to once-per-step later is a
 * one-line change in WorkflowService::remind().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
            $table->unsignedTinyInteger('reminder_sent_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'reminder_sent_step']);
        });
    }
};
