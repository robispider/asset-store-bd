<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->timestamp('return_requested_at')->nullable();
            $table->uuid('return_document_id')->nullable();
            $table->foreign('return_document_id')->references('id')->on('gov_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->dropForeign(['return_document_id']);
            $table->dropColumn(['return_requested_at', 'return_document_id']);
        });
    }
};
