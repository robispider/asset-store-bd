<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Finance Division item master (GGSSII). Codes are never deleted or reused; the UNSPSC code is a
 * soft link, like the rest of the catalogue, so it survives catalogue re-imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_finance_items')) {
            return;
        }
        Schema::create('gov_finance_items', function (Blueprint $table) {
            $table->char('gov_code', 6)->primary();
            $table->string('group_name', 191);
            $table->string('subgroup_name', 191);
            $table->string('item_name', 255);
            $table->string('unspsc_code', 50)->nullable();
            $table->string('status', 16)->default('held');
            $table->string('origin', 30)->default('manual_extract');
            $table->text('source_reference')->nullable();
            $table->text('authority_reference')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['unspsc_code', 'status'], 'finance_unspsc_status');
            $table->index(['status', 'gov_code'], 'finance_status_code');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gov_finance_items MODIFY gov_code CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_finance_items');
    }
};
