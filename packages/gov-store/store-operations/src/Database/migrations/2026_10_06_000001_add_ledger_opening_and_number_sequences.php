<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_inventory_movements', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });

        Schema::create('gov_store_document_sequences', function (Blueprint $table) {
            $table->string('prefix', 8);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['prefix', 'sequence_year'], 'gov_store_document_sequence_pk');
        });

        Schema::create('gov_store_ledger_openings', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->primary();
            $table->uuid('document_id')->unique();
            $table->dateTime('opened_at');
            $table->unsignedInteger('opened_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_store_ledger_openings');
        Schema::dropIfExists('gov_store_document_sequences');
        Schema::table('gov_inventory_movements', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
