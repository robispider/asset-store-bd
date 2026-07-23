<?php

namespace GovStore\CustomRequests\Adapters;

use GovStore\CustomRequests\Contracts\RequestableInterface;
use App\Models\AssetModel;
use App\Models\User;
use Exception;

class AssetModelAdapter implements RequestableInterface
{
    protected $assetModel;

    public function __construct(AssetModel $assetModel)
    {
        $this->assetModel = $assetModel;
    }

    public function getModel() { return $this->assetModel; }
    
    public function getDisplayName(): string { return $this->assetModel->name ?: 'Unknown Asset Model'; }
    
    public function getType(): string { return 'Hardware'; }
    
    public function getAvailableQuantity(): int 
    { 
        // Dynamic aggregate count of unassigned physical machines
        return \App\Models\Asset::where('model_id', $this->assetModel->id)
            ->whereNull('assigned_to')
            ->where('requestable', 1)
            ->count(); 
    }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // This should never be called directly. 
        // Asset Models require explicit serial assignment in the Fulfillment Engine.
        throw new Exception("Asset Models cannot be blindly checked out. They require specific serial assignment.");
    }
}