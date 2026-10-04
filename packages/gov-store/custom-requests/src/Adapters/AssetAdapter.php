<?php

namespace GovStore\CustomRequests\Adapters;

use App\Models\Asset;
use App\Models\User;
use GovStore\CustomRequests\Contracts\RequestableInterface;

class AssetAdapter implements RequestableInterface
{
    protected $asset;

    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
    }

    public function getModel()
    {
        return $this->asset;
    }

    public function getDisplayName(): string
    {
        return $this->asset->present()->name ?: __('requestlabels::requests.unknown_item');
    }

    public function getType(): string
    {
        return 'Asset';
    }

    public function getAvailableQuantity(): int
    {
        return $this->asset->requestable && $this->asset->availableForCheckout() ? 1 : 0;
    }

    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool
    {
        throw new \LogicException(__('requestlabels::requests.serial_fulfillment_required'));
    }
}
