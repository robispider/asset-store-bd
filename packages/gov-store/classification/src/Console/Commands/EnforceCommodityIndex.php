<?php

namespace GovStore\Classification\Console\Commands;

use GovStore\Classification\Services\ProductTypeCommodity;
use Illuminate\Console\Command;

class EnforceCommodityIndex extends Command
{
    protected $signature = 'catalog:commodity-index';

    protected $description = 'Apply UNIQUE(category_id) to gov_catalog_snipe_mappings once every product type has one UNSPSC code.';

    public function handle(ProductTypeCommodity $commodity): int
    {
        if ($commodity->enforceUniqueIndex()) {
            $this->info('Every product type has at most one UNSPSC code; the unique index is in place.');

            return self::SUCCESS;
        }
        $this->error('These product types still carry several UNSPSC codes (category id => codes):');
        foreach ($commodity->categoriesWithSeveralCodes() as $category => $total) {
            $this->line("  {$category} => {$total}");
        }

        return self::FAILURE;
    }
}
