<?php

namespace GovStore\Tracking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryMaterializedAgainstProgramme
{
    use Dispatchable, SerializesModels;

    public string $trackingCode;
    public int $categoryId;
    public int $modelId;             // New: Specific core models.id
    public int $manufacturerId;      // New: Specific core manufacturers.id
    public int $locationId;          // core locations.id (The receiving warehouse)
    public int $quantity;            // Total unit count received
    public float $totalCost;         // Cumulative line-item purchase cost
    public int $supplierId;          // core suppliers.id (Vendor)
    public int $actorId;             // core users.id (The Storekeeper)
    public string $grnReference;      // GRN Voucher serial number for audit trail
    public array $associatables;     // Optional polymorphic array of asset/ledger IDs
    public ?string $overrideReason;  // Justification string if target was exceeded

    /**
     * Instantiate the multi-dimensional transaction contract payload.
     */
    public function __construct(
        string $trackingCode,
        int $categoryId,
        int $modelId,
        int $manufacturerId,
        int $locationId,
        int $quantity,
        float $totalCost,
        int $supplierId,
        int $actorId,
        string $grnReference,
        array $associatables = [],
        ?string $overrideReason = null
    ) {
        $this->trackingCode = $trackingCode;
        $this->categoryId = $categoryId;
        $this->modelId = $modelId;
        $this->manufacturerId = $manufacturerId;
        $this->locationId = $locationId;
        $this->quantity = $quantity;
        $this->totalCost = $totalCost;
        $this->supplierId = $supplierId;
        $this->actorId = $actorId;
        $this->grnReference = $grnReference;
        $this->associatables = $associatables;
        $this->overrideReason = $overrideReason;
    }
}