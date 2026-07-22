<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\DTOs\CompiledProfile;
use Illuminate\Support\Facades\DB;
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

        // 1. Resolve and parse the frozen compiled snapshot
        $snapshot = $document->compiled_profile_snapshot;
        if (empty($snapshot)) {
            throw new Exception("Document is missing its immutable compiled profile snapshot.");
        }
        $profile = new CompiledProfile($snapshot);

        DB::transaction(function () use ($document, $profile, $userId) {
            
            // 2. Lock the document status
            $document->update(['status' => DocumentState::POSTED->value]);

            // 3. Process each line item based on its compiled capabilities
            foreach ($document->items as $item) {
                // Fetch active capabilities for this line from the snapshot
                $capabilities = $profile->getCapabilitiesForProduct($item->product_type, $item->product_id);

                foreach ($capabilities as $capCode => $config) {
                    if (!$capCode) {
                        continue;
                    }

                    // Instantiate the capability plugin class via Registry
                    $capability = CapabilityRegistry::make($capCode);

                    // Execute step
                    $capability->execute($item, $config);
                }
            }

            // 4. Record final Posted Timeline Event
            $document->timelines()->create([
                'state'   => DocumentState::POSTED->value,
                'user_id' => $userId,
                'notes'   => "Document finalized and posted to ledger."
            ]);
        });
    }
}