<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Services\LedgerPostingService;
use Exception;

class TransferInventoryCapability implements CapabilityInterface
{
    protected LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function getRequirements(array $config = []): array { return ['destination_location_id']; }
    public function validate(array $data, array $config = []): array { return []; }

    /**
     * Executes double-entry dual-ledger commitment.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        $destinationLocationId = $item->metadata()->where('field_key', 'destination_location_id')->first()?->value;

        if (!$destinationLocationId) {
            throw new Exception("Destination location is required for inter-location transfers.");
        }

        // 1. OUT movement from Source Office
        $this->ledger->postMovement(
            $item->product_type,
            $item->product_id,
            'OUT',
            $item->quantity,
            $document,
            $document->company_id ?? null,
            $document->location_id ?? null,
            auth()->id() ?? 1
        );

        // 2. IN movement to Destination Office
        $this->ledger->postMovement(
            $item->product_type,
            $item->product_id,
            'IN',
            $item->quantity,
            $document,
            $document->company_id ?? null,
            (int) $destinationLocationId,
            auth()->id() ?? 1
        );
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        $locations = \App\Models\Location::orderBy('name')->get();
        return view('storeops::capabilities.transfer_destination', [
            'item' => $item, 
            'locations' => $locations
        ])->render();
    }
}