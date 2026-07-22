<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\Profile;
use Illuminate\Support\Facades\DB;
use Exception;

class ProfileCompilerService
{
    /**
     * In-memory request cache. Prevents duplicate database queries 
     * when processing multiple items of the same category in a single document.
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
     * The 4-Layer Resolution Engine. Merges capability codes and payloads Top-Down.
     */
    public function compileItem(string $productType, int $productId): array
    {
        // 1. Resolve layers dynamically
        $layers = [
            $this->resolveGlobal(),
            $this->resolveMajorType($productType),
            $this->resolveCategory($productType, $productId),
            $this->resolveModel($productType, $productId) // Optional
        ];

        $mergedCapabilities = [];

        // 2. Top-down CSS-style merge
        foreach (array_filter($layers) as $profile) {
            foreach ($profile->capabilities as $cap) {
                $code = $cap->capability_code;
                $config = $cap->config_payload ?? [];

                // Child redefinitions override parent configurations
                if (isset($mergedCapabilities[$code])) {
                    $mergedCapabilities[$code] = array_merge($mergedCapabilities[$code], $config);
                } else {
                    $mergedCapabilities[$code] = $config;
                }
            }
        }

        return $mergedCapabilities;
    }

    /**
     * Dynamically determines which Profile ID is the deepest entry point 
     * for a given line item. Uses cascading logic (Model -> Category -> Major Type -> Global).
     */
    protected function resolveStartingProfileId(string $productType, int $productId): int
    {
        // 1. Check if model-specific override profile exists (Highest Priority)
        $modelProfile = $this->resolveModel($productType, $productId);
        if ($modelProfile) {
            return $modelProfile->id;
        }

        // 2. Check if category-specific profile exists (Category Priority)
        $categoryProfile = $this->resolveCategory($productType, $productId);
        if ($categoryProfile) {
            return $categoryProfile->id;
        }

        // 3. Check if Major Type profile exists (Type Priority)
        $majorTypeProfile = $this->resolveMajorType($productType);
        if ($majorTypeProfile) {
            return $majorTypeProfile->id;
        }

        // 4. Fallback to root Global Base profile (Lowest Priority)
        $globalProfile = $this->resolveGlobal();
        if ($globalProfile) {
            return $globalProfile->id;
        }

        throw new Exception("Critical Store Engine Error: No root Global Profile found in database.");
    }

    // --- Dynamic Memoized Layer Resolvers ---

    protected function resolveGlobal(): ?Profile
    {
        // Cache in memory; only runs database query on the first call of the request
        return self::$resolvedCache['GLOBAL'] ??= Profile::with('capabilities')
            ->where('layer', 'GLOBAL')
            ->first();
    }

    protected function resolveMajorType(string $type): ?Profile
    {
        $profileName = match ($type) {
            'assetmodel'  => 'Hardware Asset', // FIXED
            'consumable'  => 'Consumable Supply',
            'accessory'   => 'Accessory',
            'component'   => 'Component',
            default       => null
        };

        if (!$profileName) {
            return null;
        }

        $cacheKey = "MAJOR_" . str_replace(' ', '_', $profileName);

        return self::$resolvedCache[$cacheKey] ??= Profile::with('capabilities')
            ->where('layer', 'MAJOR_TYPE')
            ->where('name', $profileName)
            ->first();
    }

    protected function resolveCategory(string $type, int $id): ?Profile
    {
        if ($type !== 'assetmodel') { // FIXED
            return null;
        }

        $categoryId = DB::table('models')->where('id', $id)->value('category_id');
        if (!$categoryId) {
            return null;
        }

        $categoryName = DB::table('categories')->where('id', $categoryId)->value('name');
        if (!$categoryName) {
            return null;
        }

        $cacheKey = "CAT_" . str_replace(' ', '_', $categoryName);

        return self::$resolvedCache[$cacheKey] ??= Profile::with('capabilities')
            ->where('layer', 'CATEGORY')
            ->where('name', $categoryName)
            ->first();
    }

    protected function resolveModel(string $type, int $id): ?Profile
    {
        $profileName = "Model_{$id}";
        $cacheKey = "MODEL_{$id}";

        return self::$resolvedCache[$cacheKey] ??= Profile::with('capabilities')
            ->where('layer', 'MODEL')
            ->where('name', $profileName)
            ->first();
    }
}