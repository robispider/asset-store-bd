<?php

namespace GovStore\Classification\Services\Defaults;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Additive upgrade of the same optional collection; v1 remains a frozen release. */
class CommonOfficeCollectionV2 extends CommonOfficeCollectionV1
{
    public const MANIFEST_SHA256 = '809bb4642c202cea7a7599fa3540c59a8f3cf63ebcc9537f441b962f19b4ca6a';
    protected const VERSION = 2;
    protected const SECTIONS = ['furniture', 'stationery', 'ict', 'printing', 'electrical', 'cleaning', 'general',
        'networking', 'telecom', 'av', 'pantry', 'safety', 'security', 'storage', 'maintenance', 'facilities',
        'first_aid', 'library', 'decor', 'fleet', 'fleet_tools', 'fleet_parts'];
    protected const UNITS = ['piece', 'sheet', 'roll', 'metre'];

    protected function refreshCollectionDescription(int $id, array $collection): bool
    {
        $previousPath = __DIR__.'/../../database/data/office-defaults/v1/collections.csv';
        if (! is_readable($previousPath) || ! hash_equals('879536d6b1bad0b46111b5813c2ded7a0d205a972ef0032940f3b79c9b827fd3', hash_file('sha256', $previousPath))) {
            throw new RuntimeException('The common office v1 collection defaults are missing or have changed. Restore the released bundle.');
        }
        $previous = $this->csv($previousPath,
            ['key', 'name', 'name_bn', 'description', 'description_bn', 'icon'])[0];
        $existing = DB::table('gov_catalog_collections')->where('id', $id)->lockForUpdate()
            ->first(['description', 'description_bn']);
        $changed = false;
        // Refresh each unchanged shipped description; retain every administrator edit.
        // Compare in PHP: MySQL's case-insensitive collation is not an exact comparison.
        foreach (['description', 'description_bn'] as $field) {
            if ($existing->$field === $previous[$field]) {
                DB::table('gov_catalog_collections')->where('id', $id)
                    ->update([$field => $collection[$field], 'updated_at' => now()]);
                $changed = true;
            }
        }

        return $changed;
    }
}
