<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents attached to an approval request when it is submitted (e.g. a movement's
     * supporting paperwork). Keyed to the approval_request so it's reusable across every
     * approvable module and shown to the approver on the request detail page.
     */
    public function up(): void
    {
        Schema::create('approval_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_attachments');
    }
};
