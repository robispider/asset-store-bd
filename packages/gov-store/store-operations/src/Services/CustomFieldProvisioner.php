<?php

namespace GovStore\StoreOperations\Services;

use GovStore\Metadata\Models\MetadataFieldMapping;
use App\Models\CustomField;

class CustomFieldProvisioner
{
    // Map directly to the Global Baseline fields provided by the Metadata Platform
    const IDENTIFIER_GRN        = 'govstore.baseline.grn';
    const IDENTIFIER_ALLOCATION = 'govstore.baseline.allocation';

    /**
     * Read-only helper: Resolves the physical Snipe-IT DB column name 
     * from the logical metadata identifier.
     * 
     * Uses the metadata package mapping table cleanly.
     */
    public function getDbColumn(string $identifier): ?string
    {
        $mapping = MetadataFieldMapping::where('identifier', $identifier)->first();

        if ($mapping) {
            $field = CustomField::find($mapping->custom_field_id);
            return $field ? $field->db_column : null;
        }

        return null;
    }
}