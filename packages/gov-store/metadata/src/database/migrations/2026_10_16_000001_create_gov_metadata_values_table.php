<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed GovStore metadata: one row per subject + namespace. The owner scope is recorded, never part of
 * the unique key. Payload schemas are registered by packages with MetadataValueStore::register().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_metadata_values')) {
            return;
        }
        Schema::create('gov_metadata_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('subject_type', 20);
            $table->unsignedInteger('subject_id');
            $table->string('namespace', 50);
            $table->string('scope_type', 16);
            $table->unsignedInteger('scope_id')->default(0);
            $table->json('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'namespace'], 'metadata_subject');
            $table->index(['scope_type', 'scope_id'], 'metadata_scope');
            $table->index(['namespace', 'subject_type'], 'metadata_namespace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_metadata_values');
    }
};
