<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Upgrade gov_profiles (Policies)
        // Note: Your previous schema had status/version, but we are ensuring multi-tenancy context
        Schema::table('gov_profiles', function (Blueprint $table) {
            // Who owns/authored this policy template? (NULL = System/Global)
            $table->unsignedInteger('company_id')->nullable()->after('version');
            $table->unsignedInteger('location_id')->nullable()->after('company_id');
        });

        // 2. Upgrade gov_profile_capabilities (The 3-State Rules)
        Schema::table('gov_profile_capabilities', function (Blueprint $table) {
            // Adds the 3-state toggle (Enforce, Disable, Inherit)
            $table->string('behavior', 20)->default('ENFORCE')->after('capability_code');
        });

        // 3. Upgrade gov_profile_assignments (The Mapping Scope)
        Schema::table('gov_profile_assignments', function (Blueprint $table) {
            // Differentiates between a Global assignment, Company assignment, etc.
            $table->string('scope_level', 20)->default('NATIVE')->after('target_type');
            // Store the specific ID for the scope (e.g., location_id = 5) if applicable
            $table->unsignedInteger('scope_id')->nullable()->after('scope_level');
        });
    }

    public function down()
    {
        Schema::table('gov_profile_assignments', function (Blueprint $table) {
            $table->dropColumn(['scope_level', 'scope_id']);
        });

        Schema::table('gov_profile_capabilities', function (Blueprint $table) {
            $table->dropColumn('behavior');
        });

        Schema::table('gov_profiles', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'location_id']);
        });
    }
};