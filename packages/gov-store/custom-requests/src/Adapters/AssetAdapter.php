<?php

namespace GovStore\CustomRequests\Adapters;

use GovStore\CustomRequests\Contracts\RequestableInterface;
use App\Models\Asset;
use App\Models\User;

class AssetAdapter implements RequestableInterface
{
    protected $asset;

    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
    }

    public function getModel() { return $this->asset; }
    
    public function getDisplayName(): string { return $this->asset->present()->name ?: 'Unknown Asset'; }
    
    public function getType(): string { return 'Asset'; }
    
    public function getAvailableQuantity(): int { return $this->asset->assigned_to ? 0 : 1; }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // Tell Snipe-IT to assign this asset to the user
        $this->asset->assigned_to = $targetUser->id;
        $this->asset->assigned_type = User::class;
        $this->asset->location_id = $targetUser->location_id;
        
        if ($this->asset->save()) {
            // Trigger Snipe-IT's native Actionlog so it appears in the item's history tab!
            $this->asset->logCheckout($notes, $targetUser);
            return true;
        }
        return false;
    }
}