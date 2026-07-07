<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\ApprovalPolicy;
use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;

class PolicyService
{
    /**
     * Resolves the required policy for a given catalog item.
     * Order of execution: Direct Item Override -> Category Inheritance -> Global Default (PRIMARY_ONLY)
     */
    public function resolvePolicy(string $type, int $id): string
    {
        $cleanType = strtolower($type);

        // 1. Direct Item Specific Override Check
        $itemPolicy = ApprovalPolicy::where('target_type', $cleanType)
            ->where('target_id', $id)
            ->first();

        if ($itemPolicy) {
            return $itemPolicy->policy_name;
        }

        // 2. Category Inheritance Check
        $categoryId = $this->getCategoryId($cleanType, $id);
        if ($categoryId) {
            $categoryPolicy = ApprovalPolicy::where('target_type', 'category')
                ->where('target_id', $categoryId)
                ->first();

            if ($categoryPolicy) {
                return $categoryPolicy->policy_name;
            }
        }

        // 3. Global Default fallback
        return 'PRIMARY_ONLY';
    }

    /**
     * Helper to resolve the correct category ID depending on the item type
     */
    private function getCategoryId(string $type, int $id): ?int
    {
        switch ($type) {
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