<?php

namespace GovStore\StoreOperations\Services;

use Exception;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Enums\StockableType;
use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class PostingPipelineManager
{
    /**
     * Executes the compiled materialization steps.
     * Runs strictly inside an atomic database transaction.
     */
    public function materialize(Document $document, int $userId): void
    {
        DB::transaction(function () use ($document, $userId) {
            $document = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (! in_array($document->status, ['DRAFT', 'READY'], true)) {
                throw new Exception('This document has already been posted to the ledger.');
            }
            if ($document->type === 'transfer') {
                app(TransferPostingService::class)->post($document, $userId);
                return;
            }

            if ($document->type === 'receipt' && (($document->purchase_type === 'Purchase' && ! $document->supplier_id)
                || ($document->supplier_id && ! \App\Models\Supplier::whereKey($document->supplier_id)->exists()))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['supplier_id' => __('storeops::storeops.supplier_invalid')]);
            }

            if ($document->type === 'adjustment') {
                if (! in_array($document->adjustment_reason, ['PHYSICAL_COUNT', 'DAMAGE', 'LOSS', 'EXPIRED', 'CORRECTION'], true)) {
                    throw new Exception('An adjustment requires a valid reason.');
                }
                $source = Document::withoutGlobalScopes()->whereKey($document->source_document_id)->lockForUpdate()->first();
                if (! $source || ! in_array($source->type, ['receipt', 'issue', 'adjustment'], true)
                    || $source->status !== 'POSTED' || $source->id === $document->id
                    || (int) $source->location_id !== (int) $document->location_id
                    || (int) $source->company_id !== (int) $document->company_id) {
                    throw new Exception('Adjustment source must be a posted document from the same office and company.');
                }
            }

            if ($document->items()->count() === 0) {
                throw new Exception('Cannot materialize an empty document.');
            }

            $snapshot = $document->compiled_profile_snapshot;
            if (empty($snapshot)) {
                throw new Exception('Document is missing its immutable compiled profile snapshot.');
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
                $document->update(['status' => DocumentState::POSTED->value, 'posted_by' => $userId, 'posted_at' => now(), 'drafted_by' => $document->drafted_by ?? $document->created_by]);

                // 2. Process each line item based on its compiled capabilities
                foreach ($document->items as $item) {

                    $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);
                    $stockableType = StockableType::fromString($item->product_type);
                    if (in_array($document->type, ['issue', 'adjustment'], true) && $stockableType === StockableType::ASSET_MODEL) {
                        throw new Exception('Serialized hardware cannot be adjusted as model-level stock; select stockable non-serialized items.');
                    }
                    $assetCreationConfigured = false;

                    foreach ($capabilities as $capCode => $config) {
                        if (! $capCode) {
                            continue;
                        }

                        // Safely handle both array configs (new engine) and flat formats (legacy)
                        $realCode = is_string($capCode) ? $capCode : ($config['code'] ?? null);
                        $realConfig = is_array($config) ? $config : [];

                        if (! $realCode) {
                            continue;
                        }

                        if (in_array($realCode, ['post_inventory', 'adjust_inventory'], true)) {
                            continue;
                        }
                        if ($realCode === 'create_assets') {
                            $assetCreationConfigured = true;
                            if ($document->type !== 'receipt') {
                                continue;
                            }
                        }

                        $capability = CapabilityRegistry::make($realCode);
                        $capability->execute($item, $realConfig);
                    }

                    if ($stockableType === StockableType::ASSET_MODEL && ! $assetCreationConfigured) {
                        throw new Exception('Serialized asset receipts require the asset creation rule.');
                    }

                    $direction = match ($document->type) {
                        'receipt' => 'IN',
                        'issue' => 'OUT',
                        'adjustment' => $item->metadata()->where('field_key', 'adjustment_direction')->value('value'),
                        default => throw new Exception('Unsupported document type for stock posting.'),
                    };
                    if (! in_array($direction, ['IN', 'OUT'], true)) {
                        throw new Exception('Every adjustment line must have a valid direction.');
                    }
                    $movementNotes = $document->type === 'adjustment'
                        ? 'Adjustment '.$document->adjustment_reason.'; source '.$document->source_document_id
                        : null;
                    $movement = app(LedgerPostingService::class)->postMovement(
                        $item->product_type, (int) $item->product_id,
                        $direction, (int) $item->quantity,
                        $document, $document->company_id ? (int) $document->company_id : null,
                        (int) $document->location_id, $userId, $movementNotes
                    );

                    // ========================================================================
                    // HANDSHAKE B: THE UNIFIED EVENT DISPATCHER (Corrected Signature v3)
                    // ========================================================================
                    // Triggers synchronously if the Tracking Package is installed & code exists
                    if ($document->type === 'receipt' && ! empty($trackingCode) && class_exists('\GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme')) {

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
                                'id' => $assetId,
                            ];
                        }

                        // D. Calculate the Total Financial Cost for budget depletion tracking
                        $totalCost = (float) ($item->quantity * ($item->unit_cost ?? 0.0));

                        // E. Safely resolve Supplier ID dynamically if it exists, or fallback to type-safe 0
                        $supplierId = isset($document->supplier_id) ? (int) $document->supplier_id : 0;

                        // F. Dispatch the event with the exact 12-argument constructor signature
                        event(new InventoryMaterializedAgainstProgramme(
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
                            null,              // Argument #12: (string|null)
                            (string) $movement->id // Durable delivery identity, distinct for each receipt line
                        ));
                    }
                }

                // 3. Record final Posted Timeline Event
                $document->timelines()->create([
                    'state' => DocumentState::POSTED->value,
                    'user_id' => $userId,
                    'notes' => 'Document finalized and posted to ledger.',
                ]);
            });
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
                } catch (Exception $e) {
                    $manufacturerId = 0;
                }
            }
        }

        return [$modelId, $manufacturerId];
    }
}
