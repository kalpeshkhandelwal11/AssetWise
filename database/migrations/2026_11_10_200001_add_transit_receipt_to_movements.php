<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — In Transit + scan receipt + short received.
 *
 * Physical movements (Transfer, Return, Inter-Company) no longer apply at approval: the asset
 * goes IN_TRANSIT and the move lands when the destination (location manager / stores) scans
 * it. `status` keeps its enum (pending_approval|rejected|completed — no ALTER, SQLite) and
 * `completed` now means "approved & dispatched"; the physical state lives in receipt_status:
 *   null        → no receipt step (assignment/custodian change, or legacy rows)
 *   in_transit  → dispatched, awaiting receipt
 *   short       → reported missing/incomplete, awaiting an admin's resolution
 *   received    → landed (incl. "received later" / "accepted as-is")
 *   lost        → loss write-off approved
 *   returned    → returned to sender
 *
 * Loss write-offs (asset_loss workflow) get their own approvable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->string('receipt_status', 20)->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('previous_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_method', 20)->nullable(); // scan|manual|on_behalf
            $table->string('short_type', 20)->nullable();     // missing|incomplete
            $table->timestamp('short_reported_at')->nullable();
            $table->foreignId('short_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 30)->nullable();     // received_later|lost|returned_to_sender|accepted_as_is
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('asset_movement_batches', function (Blueprint $table) {
            $table->string('receipt_status', 20)->nullable()->index(); // in_transit|partial|closed
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::table('locations', function (Blueprint $table) {
            // Receives location transfers (scan at destination); default custodian at a plaza takeover.
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
        });

        Schema::create('asset_loss_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('asset_movements')->nullOnDelete();
            $table->string('reason', 20); // lost|stolen
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending_approval'); // pending_approval|approved|rejected
            $table->foreignId('previous_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();
            $table->foreignId('disposal_request_id')->nullable()->constrained('disposal_requests')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_loss_requests');
        Schema::table('locations', fn (Blueprint $t) => $t->dropConstrainedForeignId('manager_employee_id'));
        Schema::table('asset_movement_batches', fn (Blueprint $t) => $t->dropColumn(['receipt_status', 'dispatched_at', 'closed_at']));
        Schema::table('asset_movements', function (Blueprint $table) {
            foreach (['previous_status_id', 'received_by', 'short_reported_by', 'resolved_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['receipt_status', 'dispatched_at', 'received_at', 'receipt_method', 'short_type',
                'short_reported_at', 'resolution', 'resolution_notes', 'resolved_at']);
        });
    }
};
