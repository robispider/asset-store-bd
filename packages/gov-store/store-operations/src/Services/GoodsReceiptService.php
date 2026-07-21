<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsReceiptService
{
    protected DocumentNumberService $numberService;
    protected TenantContext $tenantContext;
    protected ProfileCompilerService $compiler;
    protected DocumentLineItemManager $lineItemManager; // Added

    /**
     * Inject the DocumentLineItemManager into the constructor
     */
    public function __construct(
        DocumentNumberService $numberService, 
        TenantContext $tenantContext,
        ProfileCompilerService $compiler,
        DocumentLineItemManager $lineItemManager // Added
    ) {
        $this->numberService = $numberService;
        $this->tenantContext = $tenantContext;
        $this->compiler = $compiler;
        $this->lineItemManager = $lineItemManager; // Added
    }

    /**
     * Saves document details, normalizes columns, and saves the Compiled Profile Snapshot.
     */
    public function saveDraft(array $headerData, array $rawLines, int $userId, ?Document $document = null): Document
    {
        return DB::transaction(function () use ($headerData, $rawLines, $userId, $document) {
            
            if ($document && $document->status !== DocumentState::DRAFT->value) {
                throw new Exception("This document is locked and can no longer be edited.");
            }

            // 1. Create or Update Header
            if (!$document) {
                $headerData['document_number'] = $this->numberService->generate('GR', 'gov_documents', 'document_number');
                $headerData['type'] = 'receipt';
                $headerData['status'] = DocumentState::DRAFT->value;
                $headerData['company_id'] = $this->tenantContext->companyId;
                $headerData['location_id'] = $this->tenantContext->locationId;
                $headerData['created_by'] = $userId;
                
                $document = Document::create($headerData);
                $document->transitionTo(DocumentState::DRAFT, $userId, 'Document workspace initialized.');
            } else {
                $document->update($headerData);
            }

            // 2. PROCESS AND NORMALIZE LINES (Translates type -> product_type, qty -> quantity, and merges duplicates)
            $processedLines = $this->lineItemManager->processLines($rawLines, 'IN');

            $document->items()->delete();
            if (!empty($processedLines)) {
                // Save the normalized, database-compatible columns
                $document->items()->createMany($processedLines);
            }

            // Refresh the relationship inside Eloquent's memory before compiling the snapshot
            $document->load('items');

            // 3. Compile and save the frozen snapshot
            $snapshot = $this->compiler->compileDocument($document);
            $document->update([
                'compiled_profile_snapshot' => $snapshot
            ]);

            return $document;
        });
    }

    /**
     * Transitions the document to POSTED, generating Ledger entries.
     */
    public function post(Document $document, int $userId): void
    {
        if ($document->status === DocumentState::POSTED->value) {
            throw new Exception("This Goods Receipt has already been posted.");
        }

        if ($document->items()->count() === 0) {
            throw new Exception("A Goods Receipt must contain at least one valid item to post to the ledger.");
        }

        DB::transaction(function () use ($document, $userId) {
            foreach ($document->items as $item) {
                $this->ledger->postMovement(
                    $item->product_type,
                    $item->product_id,
                    'IN',
                    $item->quantity,
                    $document,
                    $document->company_id,
                    $document->location_id,
                    $userId
                );
            }

            $document->transitionTo(DocumentState::POSTED, $userId, 'Document posted to ledger.');
        });
    }
}