<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safely patches the production database by removing the legacy physical 
     * office column, migrating the system to the new Operation Unit matrix.
     */
    public function up(): void
    {
        // Check if the legacy column still exists on the production/staging server
        if (Schema::hasColumn('gov_initiatives', 'manager_location_id')) {
            Schema::table('gov_initiatives', function (Blueprint $table) {
                
                // 1. Drop the foreign key constraint first
                // Laravel convention: table_column_foreign
                $table->dropForeign(['manager_location_id']);
                
                // 2. Drop the column
                $table->dropColumn('manager_location_id');
            });
        }
    }

    /**
     * Rollback protection in case of deployment failure.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('gov_initiatives', 'manager_location_id')) {
            Schema::table('gov_initiatives', function (Blueprint $table) {
                $table->unsignedInteger('manager_location_id')->nullable();
                $table->foreign('manager_location_id')->references('id')->on('locations')->onDelete('restrict');
            });
        }
    }
};