<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * approval_workflows.module was an enum, edited in place every time a module was added
 * (depreciation, asset_creation) — which never reaches an install whose table already
 * exists. New modules (asset_loss, takeover, handover) are coming, so make it a plain
 * string; ApprovalWorkflow::MODULES (validated in WorkflowController) is the allow-list.
 *
 * Schema-builder ->change() (not a raw ALTER … MODIFY) so it also runs on the SQLite test DB,
 * where Laravel rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->string('module', 50)->change();
        });
    }

    public function down(): void
    {
        // Irreversible in practice (rows may hold modules the old enum lacks) — leave as string.
    }
};
