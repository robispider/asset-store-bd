<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\ProfileAssignment;
use Illuminate\Support\Facades\DB;
use Exception;

class ProfileCompilerService
{
    /**
     * In-memory request cache for lightning-fast compilation of identical items.
     */
    protected static array $resolvedCache = [];

    /**
     * Compiles the immutable JSON snapshot for the entire document.
     */
    public function compileDocument(Document $document): array
    {
        $snapshot = [];

        foreach ($document->items as $item) {
            $itemKey = "{$item->product_type}_{$item->product_id}";
            $snapshot[$itemKey] = $this->compileItem($item->product_type, $item->product_id);
        }

        return ['items' => $snapshot];
    }

    /**
     * The Assignment Resolution Engine.
     * Finds active policies linked to the Model, Category, or Global scope and merges them.
     */
    public function compileItem(string $productType, int $productId): array
    {
        // 1. Resolve the active profile ID for this product
        $activeProfileId = $this->resolveAssignedProfileId($productType, $productId);

        if (!$activeProfileId) {
            return []; // No rules assigned
        }

        $mergedCapabilities = [];

        // 2. Fetch assigned capabilities (plugins) from the active policy
        $capabilities = DB::table('gov_profile_capabilities')
            ->where('profile_id', $activeProfileId)
            ->get();

        foreach ($capabilities as $cap) {
            $code = $cap->capability_code;
            $config = json_decode($cap->config_payload ?? '{}', true);

            // Child configs overwrite parent configs for the same capability
            if (isset($mergedCapabilities[$code])) {
                $mergedCapabilities[$code] = array_merge($mergedCapabilities[$code], $config);
            } else {
                $mergedCapabilities[$code] = $config;
            }
        }

        return $mergedCapabilities;
    }

    /**
     * Dynamically determines which Published Policy is actively assigned 
     * to a given line item. Uses cascading logic: Model Override -> Category Default.
     */
    protected function resolveAssignedProfileId(string $productType, int $productId): ?int
    {
        $cacheKey = "{$productType}_{$productId}";

        if (isset(self::$resolvedCache[$cacheKey])) {
            return self::$resolvedCache[$cacheKey];
        }

        $profileId = null;

        if ($productType === 'assetmodel' || $productType === 'asset_model') {
            // 1. Check for Model-Level Override Assignment
            $profileId = $this->getActiveAssignment('App\Models\AssetModel', $productId);

            if (!$profileId) {
                // 2. Fallback to Category-Level Assignment
                $categoryId = DB::table('models')->where('id', $productId)->value('category_id');
                if ($categoryId) {
                    $profileId = $this->getActiveAssignment('App\Models\Category', $categoryId);
                }
            }
        } else {
            // Consumables, Accessories, Components (Check Category Assignment)
            $modelClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($productType);
            if ($modelClass) {
                $categoryId = DB::table((new $modelClass)->getTable())->where('id', $productId)->value('category_id');
                if ($categoryId) {
                    $profileId = $this->getActiveAssignment('App\Models\Category', $categoryId);
                }
            }
        }

        self::$resolvedCache[$cacheKey] = $profileId;
        return $profileId;
    }

    /**
     * Helper to fetch the currently active profile assignment for a target.
     * Enforces the 'effective_from' and 'effective_to' date constraints.
     */
    protected function getActiveAssignment(string $targetType, int $targetId): ?int
    {
        $now = now();
        
        return ProfileAssignment::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>', $now);
            })
            ->value('profile_id');
    }
}