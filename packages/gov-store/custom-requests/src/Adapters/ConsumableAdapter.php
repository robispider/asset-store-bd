<?php

namespace GovStore\CustomRequests\Adapters;

use App\Models\Consumable;
use App\Models\User;
use GovStore\CustomRequests\Contracts\RequestableInterface;

class ConsumableAdapter implements RequestableInterface
{
    protected $consumable;

    public function __construct(Consumable $consumable)
    {
        $this->consumable = $consumable;
    }

    public function getModel()
    {
        return $this->consumable;
    }

    public function getDisplayName(): string
    {
        return $this->consumable->name;
    }

    public function getType(): string
    {
        return 'Consumable';
    }

    public function getAvailableQuantity(): int
    {
        return $this->consumable->numRemaining();
    }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // Stock was already decremented by the Goods Issue ledger.
        // Trigger Snipe-IT's native logger so it appears in the consumable's history tab
        $this->consumable->logCheckout($notes, $targetUser, null, [], $quantity);

        return true;
    }
}
