<?php

namespace GovStore\CustomRequests\Adapters;

use GovStore\CustomRequests\Contracts\RequestableInterface;
use App\Models\Accessory;
use App\Models\User;

class AccessoryAdapter implements RequestableInterface
{
    protected $accessory;

    public function __construct(Accessory $accessory)
    {
        $this->accessory = $accessory;
    }

    public function getModel() { return $this->accessory; }
    
    public function getDisplayName(): string { return $this->accessory->name; }
    
    public function getType(): string { return 'Accessory'; }
    
    public function getAvailableQuantity(): int { return $this->accessory->numRemaining(); }

   public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // Re-check stock at fulfillment time to avoid over-issuing beyond availability.
        if ($this->accessory->numRemaining() < $quantity) {
            return false;
        }

        // Use Snipe-IT's native relationship safely
        $this->accessory->users()->attach($targetUser->id, [
            'assigned_to' => $targetUser->id,
            'note' => $notes,
        ]);
        
        // Trigger Snipe-IT's native logger
        $this->accessory->logCheckout($notes, $targetUser);
        return true;
    }
}