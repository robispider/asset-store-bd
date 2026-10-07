<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;

class PolicyService
{
    /**
     * Resolves the required policy for a given catalog item.
     * Order of execution: Direct Item Override -> Category Inheritance -> Global Default (PRIMARY_ONLY)
     */
    public function resolvePolicy(string $type, int $id, int $quantity = 1): string
    {
        $cleanType = strtolower($type);

        // 1. Direct Item Specific Override Check
        $itemPolicy = ApprovalPolicy::where('target_type', $cleanType)
            ->where('target_id', $id)
            ->first();

        if ($itemPolicy) {
            return $this->applyThreshold($itemPolicy, $cleanType, $id, $quantity);
        }

        // 2. Category Inheritance Check
        $categoryId = $this->getCategoryId($cleanType, $id);
        if ($categoryId) {
            $categoryPolicy = ApprovalPolicy::where('target_type', 'category')
                ->where('target_id', $categoryId)
                ->first();

            if ($categoryPolicy) {
                return $this->applyThreshold($categoryPolicy, $cleanType, $id, $quantity);
            }
        }

        // 3. Global Default fallback
        return 'PRIMARY_ONLY';
    }

    private function applyThreshold(ApprovalPolicy $policy, string $type, int $id, int $quantity): string
    {
        abort_unless(in_array($policy->policy_name, RequestWorkflow::POLICIES), 409);
        if ($policy->threshold_qty !== null && $quantity >= (int) $policy->threshold_qty) {
            return 'PRIMARY_AND_FINAL';
        }
        if ($policy->threshold_value !== null) {
            $model = app(RequestInventory::class)->validateItem($type, $id);
            $cost = $type === 'asset_model' ? Asset::where('model_id', $id)
                ->where('location_id', app(TenantContext::class)->locationId)->max('purchase_cost') : $model->purchase_cost;
            // Unknown valuations must not bypass a value threshold.
            if ($cost === null || $quantity * (float) $cost >= (float) $policy->threshold_value) {
                return 'PRIMARY_AND_FINAL';
            }
        }

        return $policy->policy_name;
    }

    /**
     * Helper to resolve the correct category ID depending on the item type
     */
    private function getCategoryId(string $type, int $id): ?int
    {
        switch ($type) {
            case 'assetmodel':
            case 'asset_model':
                return AssetModel::find($id)?->category_id;
            case 'component':
                return Component::find($id)?->category_id;
            case 'license':
                return License::find($id)?->category_id;
            case 'asset':
                $asset = Asset::with(['model'])->find($id);

                return $asset && $asset->model ? $asset->model->category_id : null;
            case 'accessory':
                $accessory = Accessory::find($id);

                return $accessory ? $accessory->category_id : null;
            case 'consumable':
                $consumable = Consumable::find($id);

                return $consumable ? $consumable->category_id : null;
            default:
                return null;
        }
    }
}
