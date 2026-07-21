<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Services\LedgerPostingService;
use Exception;

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
     * Executes symmetrical Kardex ledger posting.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        $direction = $document->type === 'receipt' ? 'IN' : 'OUT';

        // Delegate with safe nullable parameters
        $this->ledger->postMovement(
            $item->product_type,
            $item->product_id,
            $direction,
            $item->quantity,
            $document,
            $document->company_id ?? null,
            $document->location_id ?? null,
            auth()->id() ?? 1
        );
    }
}