<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('audit_type_id')->constrained('audit_types')->restrictOnDelete();
            $table->text('description')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Scope filters (company_id, category_id, asset_type_id, status_id, location_id,
            // building_id, department_id, branch_id) — frozen once the campaign is activated.
            $table->json('scope')->nullable();

            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_campaigns');
    }
};
