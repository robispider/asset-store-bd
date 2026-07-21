<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Document References (Challans, Nothis, Work Orders)
        Schema::create('gov_document_references', function (Blueprint $table) {
            $table->id();
            $table->string('document_type'); // Polymorphic
            $table->uuid('document_id');
            $table->string('reference_type'); // e.g., 'CHALLAN', 'NOTHI', 'PO'
            $table->string('reference_number');
            $table->date('reference_date')->nullable();
            $table->timestamps();
            
            $table->index(['document_type', 'document_id']);
        });

        // 2. Document Attachments (PDFs, Scans)
        Schema::create('gov_document_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type');
            $table->uuid('document_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            
            $table->index(['document_type', 'document_id']);
        });

        // 3. Document Timelines (The Audit Trail)
        Schema::create('gov_document_timelines', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->uuid('document_id');
            $table->string('state'); // DRAFT, READY, POSTED, CANCELLED
            $table->unsignedInteger('user_id');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_document_timelines');
        Schema::dropIfExists('gov_document_attachments');
        Schema::dropIfExists('gov_document_references');
    }
};