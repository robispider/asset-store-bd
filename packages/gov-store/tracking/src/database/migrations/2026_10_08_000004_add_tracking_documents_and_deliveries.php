<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_key', 64)->unique();
            $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->cascadeOnDelete();
            $table->timestamp('created_at');
        });
        if (! Schema::hasTable('gov_tracking_documents')) {
            Schema::create('gov_tracking_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size');
                $table->string('mime_type');
                $table->unsignedInteger('uploaded_by');
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('gov_tracking_documents', 'tracking_code_id')) {
            // Do not guess the meaning of historical reference IDs.
            Schema::table('gov_tracking_documents', function (Blueprint $table) {
                $table->foreignId('tracking_code_id')->nullable()->constrained('gov_tracking_codes')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Evidence is retained on rollback; reconcile it through an authorized workflow.
        Schema::dropIfExists('gov_tracking_deliveries');
    }
};
