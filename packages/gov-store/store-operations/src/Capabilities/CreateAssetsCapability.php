<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Services\CustomFieldProvisioner;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateAssetsCapability implements CapabilityInterface
{
    protected CustomFieldProvisioner $fieldProvisioner;

    public function __construct(CustomFieldProvisioner $fieldProvisioner)
    {
        $this->fieldProvisioner = $fieldProvisioner;
    }

    public function getRequirements(array $config = []): array { return []; }
    public function validate(array $data, array $config = []): array { return []; }

    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        $quantity = (int) $item->quantity;

        if ($quantity <= 0) {
            return;
        }

        // --- READ-ONLY METADATA RESOLUTION ---
        // Retrieve the physical columns dynamically from the Metadata Platform mappings
        $grnColumn = $this->fieldProvisioner->getDbColumn(CustomFieldProvisioner::IDENTIFIER_GRN);
        $allocationColumn = $this->fieldProvisioner->getDbColumn(CustomFieldProvisioner::IDENTIFIER_ALLOCATION);

        // Check if the Storekeeper attached a "Special Allocation" reference to the GRN
        $allocationRef = $document->references->where('reference_type', 'Special Allocation')->first();
        $allocationCode = $allocationRef ? $allocationRef->reference_number : null;

        // Group EAV metadata entries by row_index
        $metadata = $item->metadata()->get()->groupBy('row_index');

        foreach (range(0, $quantity - 1) as $r) {
            $rowMeta = $metadata->get($r);

            $serial = null;
            $warrantyMonths = null;
            $tag = null;

            if ($rowMeta) {
                $serial = $rowMeta->where('field_key', 'serial_number')->first()?->value;
                $warrantyMonths = $rowMeta->where('field_key', 'warranty_months')->first()?->value;
                $tag = $rowMeta->where('field_key', 'asset_tag')->first()?->value;
            }

            if (!$serial) {
                $serial = 'SN-AUTO-' . $document->getDocumentNumber() . '-' . $item->product_id . '-' . $r;
            }

            // FIXED: If custom asset tag is not captured in EAV, auto-generate a unique traceable tag
            if (!$tag) {
                $tag = 'TAG-AUTO-' . $document->getDocumentNumber() . '-' . $item->product_id . '-' . uniqid();
            }

            // 1. INSTANTIATE NATIVE ASSET (Purely Transactional)
            $asset = new Asset();
            $asset->model_id    = $item->product_id;
            $asset->serial      = $serial;
            $asset->asset_tag   = $tag; // FIXED: Assigned required unique asset tag
            $asset->status_id   = $config['status_id'] ?? 1; // Default "Ready to Deploy"
            $asset->company_id  = $document->company_id;
            $asset->location_id = $document->location_id;
            
            // Map Warranty
            if ($warrantyMonths) {
                $asset->warranty_months = (int) $warrantyMonths;
            }

            // --- 2. APPLY AUDIT TAGS TO RESOLVED COLUMNS ---
            if ($grnColumn) {
                $asset->{$grnColumn} = $document->getDocumentNumber();
            }
            if ($allocationColumn && $allocationCode) {
                $asset->{$allocationColumn} = $allocationCode;
            }

            if (!$asset->save()) {
                // FIXED: Extract dynamic validation errors directly from Snipe-IT model for advanced debugging
                $errors = method_exists($asset, 'getErrors') ? $asset->getErrors()->all() : [];
                $errorString = !empty($errors) ? ' Validation Errors: ' . implode(', ', $errors) : '';
                
                throw new Exception("Failed to instantiate native Snipe-IT asset for serial [{$serial}].{$errorString}");
            }

            // 3. LINK BRIDGE TABLE
            DB::table('gov_asset_registrations')->insert([
                'intake_item_id' => $item->id,
                'asset_id'       => $asset->id,
                'asset_tag'      => $asset->asset_tag,
                'serial_number'  => $asset->serial,
                'created_at'     => now(),
            ]);

            // 4. TRIGGER NATIVE ACTION LOGGER
            $asset->logCheckout("Received under dynamic GRN: {$document->document_number}", auth()->user() ?? app(\App\Models\User::class)->first());
        }
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        return ''; 
    }
}