<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_documents', function (Blueprint $table) {
            $table->uuid('source_document_id')->nullable()->index();
            $table->string('adjustment_reason', 32)->nullable();
            $table->unsignedInteger('issued_to_user_id')->nullable()->index();
            $table->string('issue_department', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gov_documents', function (Blueprint $table) {
            $table->dropColumn(['source_document_id', 'adjustment_reason', 'issued_to_user_id', 'issue_department']);
        });
    }
};
