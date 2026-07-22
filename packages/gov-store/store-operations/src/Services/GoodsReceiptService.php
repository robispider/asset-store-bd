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
    protected DocumentLineItemManager $lineItemManager;
    protected PostingPipelineManager $pipelineManager;

    public function __construct(
        DocumentNumberService $numberService, 
        TenantContext $tenantContext,
        ProfileCompilerService $compiler,
        DocumentLineItemManager $lineItemManager,
        PostingPipelineManager $pipelineManager
    ) {
        $this->numberService = $numberService;
        $this->tenantContext = $tenantContext;
        $this->compiler = $compiler;
        $this->lineItemManager = $lineItemManager;
        $this->pipelineManager = $pipelineManager;
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

            // 2. Process and normalize lines
            $processedLines = $this->lineItemManager->processLines($rawLines, 'IN');

            $document->items()->delete();
            if (!empty($processedLines)) {
                $document->items()->createMany($processedLines);
            }

            // Refresh relationship before snapshot compilation
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
     * Delegates posting entirely to the Materialization Pipeline Manager.
     */
    public function post(Document $document, int $userId): void
    {
        // Delegate to the composable pipeline
        $this->pipelineManager->materialize($document, $userId);
    }
}