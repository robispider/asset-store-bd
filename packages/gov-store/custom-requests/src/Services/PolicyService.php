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
        return $this->explain($type, $id, $quantity)['policy'];
    }

    /**
     * The policy and why it applies (D3): source item|category|default, the rule as set, and which
     * threshold (qty|value|cost) raised it to two approvers. Stored on the request so explanations
     * stay true after a rule changes.
     */
    public function explain(string $type, int $id, int $quantity = 1): array
    {
        $cleanType = strtolower($type);
        $reason = ['type' => $cleanType, 'id' => $id, 'qty' => $quantity, 'source' => 'default', 'base' => 'PRIMARY_ONLY', 'threshold' => null];

        // 1. Direct Item Specific Override Check
        $policy = ApprovalPolicy::where('target_type', $cleanType)->where('target_id', $id)->first();
        if ($policy) {
            $reason['source'] = 'item';
        } else {
            // 2. Category Inheritance Check
            $categoryId = $this->getCategoryId($cleanType, $id);
            $reason['category_id'] = $categoryId;
            $policy = $categoryId ? ApprovalPolicy::where('target_type', 'category')->where('target_id', $categoryId)->first() : null;
            if ($policy) {
                $reason['source'] = 'category';
            }
        }
        return $this->withPolicy($reason, $policy);
    }

    /**
     * explain() for a page of catalogue rows in two queries, so every card can say who approves it.
     * Rows carry type, id, category_id and the quantity to judge (1, or what is in the draft).
     *
     * @param  iterable<object{type:string, id:int, category_id:?int, qty:int}>  $rows
     * @return array<string, array> keyed "type:id"
     */
    public function explainMany(iterable $rows): array
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            return [];
        }
        $items = ApprovalPolicy::query()->where(function ($q) use ($rows) {
            foreach ($rows->groupBy('type') as $type => $group) {
                $q->orWhere(fn ($q) => $q->where('target_type', $type)->whereIn('target_id', $group->pluck('id')));
            }
        })->get()->keyBy(fn ($policy) => $policy->target_type.':'.$policy->target_id);
        $categories = ApprovalPolicy::where('target_type', 'category')
            ->whereIn('target_id', $rows->pluck('category_id')->filter()->unique())->get()->keyBy('target_id');

        return $rows->mapWithKeys(function ($row) use ($items, $categories) {
            $key = $row->type.':'.$row->id;
            $reason = ['type' => $row->type, 'id' => (int) $row->id, 'qty' => (int) $row->qty, 'source' => 'default', 'base' => 'PRIMARY_ONLY', 'threshold' => null];
            $policy = $items->get($key);
            if ($policy) {
                $reason['source'] = 'item';
            } else {
                $reason['category_id'] = $row->category_id;
                $policy = $row->category_id ? $categories->get($row->category_id) : null;
                if ($policy) {
                    $reason['source'] = 'category';
                }
            }

            return [$key => $this->withPolicy($reason, $policy)];
        })->all();
    }

    private function withPolicy(array $reason, ?ApprovalPolicy $policy): array
    {
        if (! $policy) {
            // 3. Global Default fallback
            return $reason + ['policy' => 'PRIMARY_ONLY'];
        }
        $reason['base'] = $policy->policy_name;
        $threshold = $this->threshold($policy, $reason['type'], $reason['id'], $reason['qty']);

        return array_merge($reason, [
            'threshold' => $threshold, 'threshold_qty' => $policy->threshold_qty, 'threshold_value' => $policy->threshold_value,
            'policy' => $threshold ? 'PRIMARY_AND_FINAL' : $policy->policy_name,
        ]);
    }

    /** Which threshold raises the rule to two approvers, if any: qty, value, or cost (unknown valuation). */
    private function threshold(ApprovalPolicy $policy, string $type, int $id, int $quantity): ?string
    {
        abort_unless(in_array($policy->policy_name, RequestWorkflow::POLICIES), 409);
        if ($policy->policy_name === 'PRIMARY_AND_FINAL') {
            return null;
        }
        if ($policy->threshold_qty !== null && $quantity >= (int) $policy->threshold_qty) {
            return 'qty';
        }
        if ($policy->threshold_value !== null) {
            $model = app(RequestInventory::class)->validateItem($type, $id);
            $cost = $type === 'asset_model' ? Asset::where('model_id', $id)
                ->where('location_id', app(TenantContext::class)->locationId)->max('purchase_cost') : $model->purchase_cost;
            // Unknown valuations must not bypass a value threshold.
            if ($cost === null) {
                return 'cost';
            }
            if ($quantity * (float) $cost >= (float) $policy->threshold_value) {
                return 'value';
            }
        }

        return null;
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
