<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Relation;
use Exception;

class PostingPipelineManager
{
    /**
     * Executes the compiled materialization steps. 
     * Runs strictly inside an atomic database transaction.
     */
    public function materialize(Document $document, int $userId): void
    {
        if ($document->status === DocumentState::POSTED->value) {
            throw new Exception("This document has already been posted to the ledger.");
        }

        if ($document->items()->count() === 0) {
            throw new Exception("Cannot materialize an empty document.");
        }

        $snapshot = $document->compiled_profile_snapshot;
        if (empty($snapshot)) {
            throw new Exception("Document is missing its immutable compiled profile snapshot.");
        }
        
        $profile = new CompiledProfile($snapshot);

        // Extract Tracking & Voucher Info for Handshake B
        $allocationRef = $document->references()->where('reference_type', 'Special Allocation')->first();
        $trackingCode = $allocationRef ? $allocationRef->reference_number : null;
        
        $challanRef = $document->references()->where('reference_type', 'Supplier Challan')->first();
        $voucherNo = $challanRef ? $challanRef->reference_number : $document->document_number;

        // ATOMIC TRANSACTION BLOCK
        DB::transaction(function () use ($document, $profile, $userId, $trackingCode, $voucherNo) {
            
            // 1. Lock the document status
            $document->update(['status' => DocumentState::POSTED->value]);

            // 2. Process each line item based on its compiled capabilities
            foreach ($document->items as $item) {
                
                $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

                foreach ($capabilities as $capCode => $config) {
                    if (!$capCode) continue;

                    // Safely handle both array configs (new engine) and flat formats (legacy)
                    $realCode = is_string($capCode) ? $capCode : ($config['code'] ?? null);
                    $realConfig = is_array($config) ? $config : [];

                    if (!$realCode) continue;

                    $capability = CapabilityRegistry::make($realCode);
                    $capability->execute($item, $realConfig);
                }

                // ========================================================================
                // HANDSHAKE B: THE UNIFIED EVENT DISPATCHER (Corrected Signature v3)
                // ========================================================================
                // Triggers synchronously if the Tracking Package is installed & code exists
                if (!empty($trackingCode) && class_exists('\GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme')) {
                    
                    // A. Resolve core category ID dynamically
                    $categoryId = $this->resolveCategoryId($item->product_type, $item->product_id);

                    // B. Resolve specific Model and Manufacturer IDs dynamically from Snipe-IT
                    [$modelId, $manufacturerId] = $this->resolveModelAndManufacturer($item->product_type, $item->product_id);

                    // C. Resolve Associatables (Polymorphic array of generated Asset IDs)
                    $associatables = [];
                    $registeredAssets = DB::table('gov_asset_registrations')
                        ->where('intake_item_id', $item->id)
                        ->pluck('asset_id');
                    
                    // If hardware, map the Asset IDs. If consumables, this safely stays empty []
                    foreach ($registeredAssets as $assetId) {
                        $associatables[] = [
                            'type' => 'App\Models\Asset',
                            'id'   => $assetId
                        ];
                    }

                    // D. Calculate the Total Financial Cost for budget depletion tracking
                    $totalCost = (float) ($item->quantity * ($item->unit_cost ?? 0.0));

                    // E. Safely resolve Supplier ID dynamically if it exists, or fallback to type-safe 0
                    $supplierId = isset($document->supplier_id) ? (int) $document->supplier_id : 0;

                    // F. Dispatch the event with the exact 12-argument constructor signature
                    event(new \GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme(
                        $trackingCode,     // Argument #1: (string)
                        $categoryId,       // Argument #2: (int)
                        $modelId,          // Argument #3: (int)
                        $manufacturerId,   // Argument #4: (int)
                        $document->location_id, // Argument #5: (int)
                        $item->quantity,   // Argument #6: (int)
                        $totalCost,        // Argument #7: (float)
                        $supplierId,       // Argument #8: (int)
                        $userId,           // Argument #9: (int - actorId)
                        $voucherNo,        // Argument #10: (string)
                        $associatables,    // Argument #11: (array)
                        null               // Argument #12: (string|null)
                    ));
                }
            }

            // 3. Record final Posted Timeline Event
            $document->timelines()->create([
                'state'   => DocumentState::POSTED->value,
                'user_id' => $userId,
                'notes'   => "Document finalized and posted to ledger."
            ]);
        });
    }

    /**
     * Helper to safely resolve the Snipe-IT Category ID regardless of polymorphic alias.
     */
    protected function resolveCategoryId(string $productType, int $productId): int
    {
        $basename = strtolower(class_basename($productType));
        
        if (in_array($basename, ['assetmodel', 'asset_model'])) {
            return DB::table('models')->where('id', $productId)->value('category_id') ?? 0;
        } else {
            // For Consumables, Accessories, Components
            $modelClass = Relation::getMorphedModel($productType) ?? $productType;
            if (class_exists($modelClass)) {
                return DB::table((new $modelClass)->getTable())->where('id', $productId)->value('category_id') ?? 0;
            }
        }
        
        return 0;
    }

    /**
     * Resiliently extracts the Model ID and Manufacturer ID dynamically from core tables.
     */
    protected function resolveModelAndManufacturer(string $productType, int $productId): array
    {
        $basename = strtolower(class_basename($productType));
        $modelId = 0;
        $manufacturerId = 0;

        if (in_array($basename, ['assetmodel', 'asset_model'])) {
            $modelId = $productId;
            $manufacturerId = DB::table('models')->where('id', $productId)->value('manufacturer_id') ?? 0;
        } else {
            // For Consumables, Accessories, Components
            $modelClass = Relation::getMorphedModel($productType) ?? $productType;
            if (class_exists($modelClass)) {
                $table = (new $modelClass)->getTable();
                $modelId = $productId;
                
                try {
                    $manufacturerId = DB::table($table)->where('id', $productId)->value('manufacturer_id') ?? 0;
                } catch (\Exception $e) {
                    $manufacturerId = 0;
                }
            }
        }

        return [$modelId, $manufacturerId];
    }
}