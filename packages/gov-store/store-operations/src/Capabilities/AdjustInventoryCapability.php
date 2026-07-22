<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Services\LedgerPostingService;

class AdjustInventoryCapability implements CapabilityInterface
{
    protected LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function getRequirements(array $config = []): array { return ['adjustment_direction']; }
    public function validate(array $data, array $config = []): array { return []; }

    /**
     * Posts stock adjustments with line-by-line directionality.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        
        // Read direction from line metadata (Defaults to IN)
        $direction = $item->metadata()->where('field_key', 'adjustment_direction')->first()?->value ?? 'IN';

        $this->ledger->postMovement(
            $item->product_type,
            $item->product_id,
            strtoupper($direction),
            $item->quantity,
            $document,
            $document->company_id ?? null,
            $document->location_id ?? null,
            auth()->id() ?? 1
        );
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        return view('storeops::capabilities.adjustment_direction', ['item' => $item])->render();
    }
}