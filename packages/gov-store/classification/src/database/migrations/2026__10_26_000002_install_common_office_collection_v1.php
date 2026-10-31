<?php

use GovStore\Classification\Services\Defaults\CommonOfficeCollectionV1;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CommonOfficeCollectionV1)->install();
    }

    public function down(): void
    {
        // Types may already be adopted and used by documents. Remove only through governed catalog actions.
        throw new RuntimeException('The common office reference collection is forward-only; adopted catalog data must be preserved.');
    }
};
