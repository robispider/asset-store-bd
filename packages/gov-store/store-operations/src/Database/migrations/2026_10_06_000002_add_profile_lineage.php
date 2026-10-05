<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_profiles', function (Blueprint $table) {
            $table->uuid('lineage_id')->nullable()->index();
        });

        foreach (DB::table('gov_profiles')->whereNull('lineage_id')->pluck('id') as $id) {
            DB::table('gov_profiles')->where('id', $id)->update(['lineage_id' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('gov_profiles', function (Blueprint $table) {
            $table->dropIndex(['lineage_id']);
            $table->dropColumn('lineage_id');
        });
    }
};
