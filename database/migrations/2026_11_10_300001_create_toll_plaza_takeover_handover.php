<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — NHAI / toll plaza takeover & handover.
 *
 * NHAI is a party (companies.party_type = external_authority). At a plaza takeover NHAI hands
 * us its assets to use — owned by NHAI, never priced, never depreciated, visible to the
 * internal company that operates the plaza (assets.operating_company_id). Separately we may
 * buy assets from the previous agency (priced, ours, depreciated). At contract end a handover
 * returns NHAI's assets and, for ours, sells them, gives them to NHAI (write-off), or moves
 * them to another plaza. Both are approval-gated (takeover / handover workflows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // internal | external_authority (NHAI) | external_agency (previous/next toll agency, buyers)
            $table->string('party_type', 30)->default('internal')->index();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_toll_plaza')->default(false);
            $table->string('chainage', 50)->nullable();
        });

        Schema::create('plaza_takeovers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();
            $table->string('kind', 20); // nhai (non-monetary, NHAI-owned) | purchase (priced, ours)
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('from_company_id')->constrained('companies')->restrictOnDelete(); // NHAI / seller
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();      // our operating company
            $table->foreignId('custodian_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('takeover_date');
            $table->string('document_no')->nullable();
            $table->string('document_path')->nullable();
            $table->json('signatories')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft'); // draft|pending_approval|approved|rejected
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plaza_takeover_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('takeover_id')->constrained('plaza_takeovers')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(0);
            $table->string('section')->nullable(); // e.g. "Toll Building", "Hybrid ETC Lanes"
            $table->string('item_name');
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('asset_type_id')->nullable()->constrained('asset_types')->nullOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('serial_number')->nullable();
            $table->string('position_label')->nullable(); // "Lane 1 (Booth)"
            $table->decimal('unit_price', 15, 2)->nullable(); // purchase takeovers only
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('location_note')->nullable(); // "IE Room", "Toll booth Entry and Exit"
            $table->string('condition', 30)->default('functional'); // functional|not_functional|partially_functional
            $table->unsignedInteger('not_functional_qty')->default(0);
            $table->decimal('warranty_years', 4, 1)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('condition', 30)->nullable();
            $table->text('condition_remarks')->nullable();
            $table->foreignId('takeover_line_id')->nullable()->constrained('plaza_takeover_lines')->nullOnDelete();
        });

        Schema::create('plaza_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();       // our company handing over
            $table->foreignId('authority_company_id')->constrained('companies')->restrictOnDelete(); // NHAI
            $table->date('handover_date');
            $table->string('document_no')->nullable();
            $table->string('document_path')->nullable();
            $table->json('signatories')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft'); // draft|pending_approval|approved|rejected|apply_failed
            $table->text('apply_error')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plaza_handover_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('plaza_handovers')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('action', 20)->nullable(); // return_to_nhai|sell|give_to_nhai|move
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->foreignId('buyer_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('buyer_name')->nullable();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_custodian_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('condition', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('nbv_snapshot', 15, 2)->nullable();
            $table->foreignId('disposal_request_id')->nullable()->constrained('disposal_requests')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('asset_movements')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['handover_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plaza_handover_lines');
        Schema::dropIfExists('plaza_handovers');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('takeover_line_id');
            $table->dropColumn(['condition', 'condition_remarks']);
        });
        Schema::dropIfExists('plaza_takeover_lines');
        Schema::dropIfExists('plaza_takeovers');
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn(['is_toll_plaza', 'chainage']));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('party_type'));
    }
};
