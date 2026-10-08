<?php

namespace GovStore\StoreOperations\Services;

use Exception;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Models\Document;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

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
    public function saveDraft(array $headerData, array $rawLines, int $userId, ?Document $document = null, string $type = 'receipt'): Document
    {
        return DB::transaction(function () use ($headerData, $rawLines, $userId, $document, $type) {
            if (! empty($headerData['supplier_id']) && ! \App\Models\Supplier::whereKey($headerData['supplier_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['supplier_id' => __('storeops::storeops.supplier_invalid')]);
            }
            if (! in_array($type, ['receipt', 'issue', 'adjustment', 'transfer'], true)) {
                throw new \InvalidArgumentException('Unsupported document type.');
            }
            if ($document) {
                $document = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if ($document->type !== $type) {
                    throw new \InvalidArgumentException('Document type mismatch.');
                }
            }

            if ($document && $document->status !== DocumentState::DRAFT->value) {
                throw new Exception('This document is locked and can no longer be edited.');
            }

            // 1. Create or Update Header
            if (! $document) {
                $prefix = match ($type) { 'receipt' => 'GR', 'issue' => 'GI', 'adjustment' => 'ADJ', 'transfer' => 'TR' };
                $headerData['document_number'] = $this->numberService->generate($prefix, 'gov_documents', 'document_number');
                $headerData['type'] = $type;
                $headerData['status'] = DocumentState::DRAFT->value;
                $headerData['company_id'] = $this->tenantContext->companyId;
                $headerData['location_id'] = $this->tenantContext->locationId;
                $headerData['created_by'] = $userId;
                $headerData['drafted_by'] = $userId;
                $headerData['managed_by'] = $userId;

                $document = Document::create($headerData);
                $document->transitionTo(DocumentState::DRAFT, $userId, __('storeops::storeops.workspace_initialized'));
            } else {
                $document->update($headerData);
            }

            // 2. Process and normalize lines
            $processedLines = $this->lineItemManager->processLines($rawLines, $type === 'receipt' ? 'IN' : ($type === 'issue' ? 'OUT' : 'ADJUSTMENT'));

            $document->items()->delete();
            if (! empty($processedLines)) {
                $document->items()->createMany($processedLines);
            }

            // Refresh relationship before snapshot compilation
            $document->load('items');

            // 3. Compile and save the frozen snapshot
            $snapshot = $this->compiler->compileDocument($document);
            $document->update([
                'compiled_profile_snapshot' => $snapshot,
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
