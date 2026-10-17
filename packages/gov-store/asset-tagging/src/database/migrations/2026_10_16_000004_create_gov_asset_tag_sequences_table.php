<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Govt. Tag counters: one row per exact 14-digit prefix (namespace asset-tag:fd18), never reset or wrapped. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_asset_tag_sequences')) {
            return;
        }
        Schema::create('gov_asset_tag_sequences', function (Blueprint $table) {
            $table->string('namespace', 50);
            $table->string('scope_key', 100);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->primary(['namespace', 'scope_key']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gov_asset_tag_sequences MODIFY namespace VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, MODIFY scope_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_asset_tag_sequences');
    }
};
