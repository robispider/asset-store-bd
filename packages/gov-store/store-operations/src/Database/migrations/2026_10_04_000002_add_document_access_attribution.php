<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_profiles', function (Blueprint $table) {
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('publish_reason')->nullable();
        });
        Schema::table('gov_documents', function (Blueprint $table) {
            $table->unsignedInteger('drafted_by')->nullable();
            $table->unsignedInteger('posted_by')->nullable();
            $table->unsignedInteger('managed_by')->nullable();
            $table->timestamp('posted_at')->nullable();
        });
        Schema::table('gov_document_attachments', fn (Blueprint $table) => $table->string('disk')->default('public'));
    }

    public function down(): void
    {
        Schema::table('gov_profiles', fn (Blueprint $table) => $table->dropColumn(['published_by', 'published_at', 'publish_reason']));
        Schema::table('gov_document_attachments', fn (Blueprint $table) => $table->dropColumn('disk'));
        Schema::table('gov_documents', fn (Blueprint $table) => $table->dropColumn(['drafted_by', 'posted_by', 'managed_by', 'posted_at']));
    }
};
