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
                // HANDSHAKE B: THE UNIFIED EVENT DISPATCHER
                // ========================================================================
                // Triggers synchronously if the Tracking Package is installed & code exists
                if (!empty($trackingCode) && class_exists('\GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme')) {
                    
                    // A. Resolve core category ID dynamically
                    $categoryId = $this->resolveCategoryId($item->product_type, $item->product_id);

                    // B. Resolve Associatables (Polymorphic array of generated Asset IDs)
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

                    // C. Dispatch the strict event contract
                    event(new \GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme(
                        $trackingCode,
                        $categoryId,
                        $document->location_id,
                        $item->quantity,
                        $userId,
                        $voucherNo,
                        $associatables
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
}