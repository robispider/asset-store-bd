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
        // Snipe-IT uses a pivot table (accessories_users) for accessories
        $this->accessory->users()->attach($this->accessory->id, [
            'accessory_id' => $this->accessory->id,
            'assigned_to' => $targetUser->id,
            'created_at' => now(),
        ]);
        
        $this->accessory->logCheckout($notes, $targetUser);
        return true;
    }
}