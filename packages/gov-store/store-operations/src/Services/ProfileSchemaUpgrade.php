<?php

namespace GovStore\StoreOperations\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive upgrades: the core migration owns profile rows and their identities. */
class ProfileSchemaUpgrade
{
    public static function plugin(): void
    {
        if (! Schema::hasColumn('gov_profiles', 'layer')) {
            Schema::table('gov_profiles', fn (Blueprint $table) => $table->string('layer')->nullable());
        }
        if (! Schema::hasColumn('gov_profile_capabilities', 'capability_code')) {
            Schema::table('gov_profile_capabilities', fn (Blueprint $table) => $table->string('capability_code')->nullable());
        }
        if (Schema::hasColumn('gov_profile_capabilities', 'capability_id')) {
            // Retain the old relationship for historical readers while enabling code-based writes.
            Schema::table('gov_profile_capabilities', fn (Blueprint $table) => $table->unsignedBigInteger('capability_id')->nullable()->change());
            foreach (DB::table('gov_capabilities')->get(['id', 'code']) as $capability) {
                DB::table('gov_profile_capabilities')->where('capability_id', $capability->id)
                    ->whereNull('capability_code')->update(['capability_code' => $capability->code]);
            }
        }
    }

    public static function catalog(): void
    {
        self::plugin();
        foreach (['scope', 'owner_type', 'owner_id', 'status', 'version'] as $column) {
            if (Schema::hasColumn('gov_profiles', $column)) {
                continue;
            }
            Schema::table('gov_profiles', function (Blueprint $table) use ($column) {
                match ($column) {
                    'scope' => $table->string($column)->default('GLOBAL'),
                    'owner_type' => $table->string($column)->nullable(),
                    'owner_id' => $table->unsignedInteger($column)->nullable(),
                    // Legacy rules need explicit review before publication; never silently activate them.
                    'status' => $table->string($column)->default('DRAFT'),
                    'version' => $table->string($column)->default('1.0.0'),
                };
            });
        }
        if (! Schema::hasTable('gov_profile_assignments')) {
            Schema::create('gov_profile_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('profile_id');
                $table->string('target_type');
                $table->unsignedInteger('target_id');
                $table->unsignedInteger('assigned_by')->nullable();
                $table->timestamp('effective_from')->useCurrent();
                $table->timestamp('effective_to')->nullable();
                $table->timestamps();
                $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
                $table->unique(['target_type', 'target_id', 'effective_to'], 'gov_active_profile_unique');
            });
        }
    }
}
