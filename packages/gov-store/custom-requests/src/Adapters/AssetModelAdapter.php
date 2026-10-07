<?php

namespace GovStore\CustomRequests\Adapters;

use App\Models\AssetModel;
use App\Models\User;
use Exception;
use GovStore\CustomRequests\Contracts\RequestableInterface;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\TenantScope\Contexts\TenantContext;

class AssetModelAdapter implements RequestableInterface
{
    protected $assetModel;

    public function __construct(AssetModel $assetModel)
    {
        $this->assetModel = $assetModel;
    }

    public function getModel()
    {
        return $this->assetModel;
    }

    public function getDisplayName(): string
    {
        return $this->assetModel->name ?: __('requestlabels::requests.unknown_item');
    }

    public function getType(): string
    {
        return 'Hardware';
    }

    public function getAvailableQuantity(): int
    {
        $office = app(TenantContext::class)->locationId;

        return $office ? app(RequestInventory::class)->available('asset_model', $this->assetModel, $office) : 0;
    }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        // This should never be called directly.
        // Asset Models require explicit serial assignment in the Fulfillment Engine.
        throw new Exception(__('requestlabels::requests.serial_fulfillment_required'));
    }
}
