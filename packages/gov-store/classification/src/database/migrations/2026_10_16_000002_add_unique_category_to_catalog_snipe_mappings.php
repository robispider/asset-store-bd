<?php

use GovStore\Classification\Services\ProductTypeCommodity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One UNSPSC code per product type. Applied only when no category carries several codes; otherwise it
 * is left for a reviewer (D-06) and applied later with `php artisan catalog:commodity-index`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gov_catalog_snipe_mappings')) {
            return;
        }
        if (! app(ProductTypeCommodity::class)->enforceUniqueIndex()) {
            Log::warning('UNIQUE(category_id) on gov_catalog_snipe_mappings was not applied: some product types carry several UNSPSC codes. Reconcile them, then run catalog:commodity-index.');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gov_catalog_snipe_mappings') && app(ProductTypeCommodity::class)->hasUniqueIndex()) {
            Schema::table('gov_catalog_snipe_mappings', fn ($table) => $table->dropUnique(ProductTypeCommodity::INDEX));
        }
    }
};
