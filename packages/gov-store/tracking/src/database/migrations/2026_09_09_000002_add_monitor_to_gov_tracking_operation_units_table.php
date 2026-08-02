<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Safely patches the production database by appending the 'MONITOR' 
     * designation to the raw MySQL ENUM column array.
     */
    public function up(): void
    {
        if (Schema::hasTable('gov_tracking_operation_units')) {
            // Raw SQL modification: 100% stable, bypasses all Doctrine DBAL limitation errors
            DB::statement("ALTER TABLE gov_tracking_operation_units MODIFY COLUMN designation ENUM('HEAD', 'OFFICER', 'SUPPORT', 'MONITOR') NOT NULL");
        }
    }

    /**
     * Rollback protection.
     */
    public function down(): void
    {
        if (Schema::hasTable('gov_tracking_operation_units')) {
            DB::statement("ALTER TABLE gov_tracking_operation_units MODIFY COLUMN designation ENUM('HEAD', 'OFFICER', 'SUPPORT') NOT NULL");
        }
    }
};