<?php

namespace GovStore\StoreOperations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use GovStore\StoreOperations\Models\InventoryMovement;

class InventoryMovementCreated
{
    use Dispatchable, SerializesModels;

    public $movement;

    public function __construct(InventoryMovement $movement)
    {
        $this->movement = $movement;
    }
}
