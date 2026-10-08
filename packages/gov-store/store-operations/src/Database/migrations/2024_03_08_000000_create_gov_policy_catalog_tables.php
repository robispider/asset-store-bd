<?php

use GovStore\StoreOperations\Services\ProfileSchemaUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ProfileSchemaUpgrade::catalog();
        // Defaults are installed through reviewed policy publication, never fixed IDs
        // or automatic adoption of whatever native categories happen to exist.
    }

    public function down(): void
    {
        // Forward-only shared upgrade; preserve rules, assignments and provenance.
    }
};