<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Services\LedgerPostingService;

class PostInventoryCapability implements CapabilityInterface
{
    protected LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function getRequirements(array $config = []): array { return []; }
    public function validate(array $data, array $config = []): array { return []; }

    /**
     * Executes symmetrical Kardex ledger posting and applies allocation tags.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        $direction = $document->type === 'receipt' ? 'IN' : 'OUT';

        // Check for Special Allocation
        $allocationRef = $document->references->where('reference_type', 'Special Allocation')->first();
        $notes = null;

        if ($allocationRef && !empty($allocationRef->reference_number)) {
            $notes = "Funded by Special Ministry Allocation: " . $allocationRef->reference_number;
        }

        // Delegate to Ledger
        $this->ledger->postMovement(
            $item->product_type,
            $item->product_id,
            $direction,
            $item->quantity,
            $document,
            $document->company_id ?? null,
            $document->location_id ?? null,
            auth()->id() ?? 1,
            $notes // Pass the allocation tag down to the ledger
        );
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        return ''; 
    }
}