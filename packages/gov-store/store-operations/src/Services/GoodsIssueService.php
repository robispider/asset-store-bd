<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsIssueService
{
    protected DocumentNumberService $numberService;
    protected TenantContext $tenantContext;
    protected LedgerPostingService $ledger;
    protected DocumentLineItemManager $lineItemManager;

    public function __construct(
        DocumentNumberService $numberService, 
        TenantContext $tenantContext,
        LedgerPostingService $ledger,
        DocumentLineItemManager $lineItemManager
    ) {
        $this->numberService = $numberService;
        $this->tenantContext = $tenantContext;
        $this->ledger = $ledger;
        $this->lineItemManager = $lineItemManager;
    }

    public function saveDraft(array $headerData, array $rawLines, int $userId, ?GoodsIssue $issue = null): GoodsIssue
    {
        return DB::transaction(function () use ($headerData, $rawLines, $userId, $issue) {
            $processedLines = $this->lineItemManager->processLines($rawLines, 'OUT');

            // [REMOVED VALUE ERROR BLOCK HERE TO ALLOW EMPTY DRAFTS]

            if (!$issue) {
                $headerData['issue_no'] = $this->numberService->generate('GI', 'gov_goods_issues', 'issue_no');
                $headerData['status'] = DocumentState::DRAFT->value;
                $headerData['company_id'] = $this->tenantContext->companyId;
                $headerData['location_id'] = $this->tenantContext->locationId;
                $headerData['created_by'] = $userId;
                
                $issue = GoodsIssue::create($headerData);
                $issue->transitionTo(DocumentState::DRAFT, $userId, 'Outbound document drafted.');
            } else {
                if ($issue->getState() !== DocumentState::DRAFT) throw new Exception("Only DRAFT documents can be edited.");
                $issue->update($headerData);
            }

            $issue->items()->delete();
            if (!empty($processedLines)) {
                $issue->items()->createMany($processedLines);
            }

            return $issue;
        });
    }

    public function post(GoodsIssue $issue, int $userId): void
    {
        if ($issue->getState() === DocumentState::POSTED) throw new Exception("Document already posted.");
        
        if ($issue->items()->count() === 0) {
            throw new Exception("A Goods Issue must contain at least one valid item to post to the ledger.");
        }

        DB::transaction(function () use ($issue, $userId) {
            foreach ($issue->items as $item) {
                $this->ledger->postMovement(
                    $item->stockable_type, $item->stockable_id, 'OUT',
                    $item->quantity, $issue,
                    $this->tenantContext->companyId, $this->tenantContext->locationId, $userId
                );
            }
            $issue->transitionTo(DocumentState::POSTED, $userId, 'Goods issued from inventory.');
        });
    }
}