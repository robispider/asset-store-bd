<?php

namespace GovStore\Tracking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryMaterializedAgainstProgramme
{
    use Dispatchable, SerializesModels;

    public string $trackingCode;
    public int $categoryId;
    public int $quantity;
    public int $actorId;
    public string $grnReference;
    public array $associatables; // Array of ['type' => 'ClassName', 'id' => int]
    public ?string $overrideReason;

    public function __construct(
        string $trackingCode,
        int $categoryId,
        int $quantity,
        int $actorId,
        string $grnReference,
        array $associatables = [],
        ?string $overrideReason = null
    ) {
        $this->trackingCode = $trackingCode;
        $this->categoryId = $categoryId;
        $this->quantity = $quantity;
        $this->actorId = $actorId;
        $this->grnReference = $grnReference;
        $this->associatables = $associatables;
        $this->overrideReason = $overrideReason;
    }
}