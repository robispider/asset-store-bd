<?php

namespace GovStore\CustomRequests\Adapters;

use App\Models\Accessory;
use App\Models\User;
use GovStore\CustomRequests\Contracts\RequestableInterface;

class AccessoryAdapter implements RequestableInterface
{
    protected $accessory;

    public function __construct(Accessory $accessory)
    {
        $this->accessory = $accessory;
    }

    public function getModel()
    {
        return $this->accessory;
    }

    public function getDisplayName(): string
    {
        return $this->accessory->name;
    }

    public function getType(): string
    {
        return 'Accessory';
    }

    public function getAvailableQuantity(): int
    {
        return $this->accessory->numRemaining();
    }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // Stock was already decremented by the Goods Issue ledger.
        // Trigger Snipe-IT's native logger
        $this->accessory->logCheckout($notes, $targetUser, null, [], $quantity);

        return true;
    }
}
