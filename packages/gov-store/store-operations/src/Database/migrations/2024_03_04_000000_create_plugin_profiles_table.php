<?php

use GovStore\StoreOperations\Services\ProfileSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ProfileSchemaUpgrade::plugin();
    }

    public function down(): void
    {
        // Shared profile data is owned by the core migration. Never drop it here.
    }
};