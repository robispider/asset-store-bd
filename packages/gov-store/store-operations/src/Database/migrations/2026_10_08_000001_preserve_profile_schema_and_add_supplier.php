<?php

use GovStore\StoreOperations\Services\ProfileSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        ProfileSchemaUpgrade::catalog();
        if (! Schema::hasColumn('gov_documents', 'destination_location_id')) {
            Schema::table('gov_documents', function (Blueprint $table) {
                $table->unsignedInteger('destination_location_id')->nullable()->index();
                $table->string('transfer_reason', 500)->nullable();
            });
        }
        Schema::table('gov_asset_registrations', fn (Blueprint $table) => $table->string('serial_number')->nullable()->change());
        if (! Schema::hasColumn('gov_documents', 'supplier_id')) {
            Schema::table('gov_documents', fn (Blueprint $table) => $table->unsignedInteger('supplier_id')->nullable()->index());
        }
        if (! Schema::hasTable('gov_document_notices')) {
            Schema::create('gov_document_notices', function (Blueprint $table) {
                $table->id();
                $table->uuid('document_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('actor_id');
                $table->string('event_key');
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'document_id']);
            });
        }
    }

    public function down(): void
    {
        // Forward-only: retaining evidence and compatibility columns is safer than data loss.
    }
};
