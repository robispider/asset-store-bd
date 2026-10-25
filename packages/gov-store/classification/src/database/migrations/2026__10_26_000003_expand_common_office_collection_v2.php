<?php

use GovStore\Classification\Services\Defaults\CommonOfficeCollectionV2;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CommonOfficeCollectionV2)->install();
    }

    public function down(): void
    {
        throw new RuntimeException('The common office reference collection is forward-only; adopted catalog data must be preserved.');
    }
};
