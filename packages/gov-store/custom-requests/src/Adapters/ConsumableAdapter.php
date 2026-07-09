<?php

namespace GovStore\CustomRequests\Adapters;

use GovStore\CustomRequests\Contracts\RequestableInterface;
use App\Models\Consumable;
use App\Models\User;

class ConsumableAdapter implements RequestableInterface
{
    protected $consumable;

    public function __construct(Consumable $consumable)
    {
        $this->consumable = $consumable;
    }

    public function getModel() { return $this->consumable; }
    
    public function getDisplayName(): string { return $this->consumable->name; }
    
    public function getType(): string { return 'Consumable'; }
    
    public function getAvailableQuantity(): int { return $this->consumable->numRemaining(); }

  public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // Re-check stock at fulfillment time to avoid over-issuing beyond availability.
        if ($this->consumable->numRemaining() < $quantity) {
            return false;
        }

        // Use Snipe-IT's native relationship.
        // We pass the Target User ID as the primary key, and let Snipe-IT map the pivot columns.
        $this->consumable->users()->attach($targetUser->id, [
            'assigned_to' => $targetUser->id,
            'note' => $notes,
        ]);
        
        // Trigger Snipe-IT's native logger so it appears in the consumable's history tab
        $this->consumable->logCheckout($notes, $targetUser);
        return true;
    }
}