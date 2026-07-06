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
        // Snipe-IT uses a pivot table (consumables_users) for consumables
        $this->consumable->users()->attach($this->consumable->id, [
            'consumable_id' => $this->consumable->id,
            'user_id' => $targetUser->id,
            'assigned_to' => $targetUser->id,
            'created_at' => now(),
        ]);
        
        $this->consumable->logCheckout($notes, $targetUser);
        return true;
    }
}