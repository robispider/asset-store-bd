<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\StoreOperations\Enums\AssignmentScope;
use GovStore\StoreOperations\Enums\CapabilityBehavior;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Relation;

class ProfileCompilerService
{
    protected TenantContext $tenantContext;
    protected static array $resolvedCache = [];

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

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
     * The Multi-Layer Merge Resolution Engine.
     * Cascades through Global -> Company -> Location -> Category -> Model.
     */
    public function compileItem(string $productType, int $productId): array
    {
        $cacheKey = "{$this->tenantContext->companyId}_{$this->tenantContext->locationId}_{$productType}_{$productId}";

        if (isset(self::$resolvedCache[$cacheKey])) {
            return self::$resolvedCache[$cacheKey];
        }

        $mergedCapabilities = [];
        
        // 1. Determine the Hierarchy Chain (What categories/models apply to this item?)
        $hierarchy = $this->resolveItemHierarchy($productType, $productId);

        // 2. Define the exact cascading resolution order (Lowest precedence to Highest)
        // Defensive: Only attempt lookup if the scope ID exists (handles Super Admins with no location/company)
        $layers = [
            'GLOBAL'   => $this->getActiveAssignment(AssignmentScope::GLOBAL, null, 'System', 1),
            'COMPANY'  => $this->tenantContext->companyId ? $this->getActiveAssignment(AssignmentScope::COMPANY, $this->tenantContext->companyId, 'Tenant', $this->tenantContext->companyId) : null,
            'LOCATION' => $this->tenantContext->locationId ? $this->getActiveAssignment(AssignmentScope::LOCATION, $this->tenantContext->locationId, 'Location', $this->tenantContext->locationId) : null,
            'CATEGORY' => $hierarchy['category_id'] ? $this->getActiveAssignment(AssignmentScope::NATIVE, null, 'App\Models\Category', $hierarchy['category_id']) : null,
            'MODEL'    => $hierarchy['is_model'] ? $this->getActiveAssignment(AssignmentScope::NATIVE, null, 'App\Models\AssetModel', $productId) : null,
        ];

        // 3. Merge rules top-down
        foreach ($layers as $layerName => $assignment) {
            if (!$assignment) {
                continue;
            }

            $profile = $assignment->profile;

            foreach ($profile->capabilities as $cap) {
                $code = $cap->capability_code;
                $behavior = $cap->behavior; // ENFORCE, DISABLE, INHERIT
                $config = $cap->config_payload ?? [];

                if ($behavior === CapabilityBehavior::INHERIT) {
                    continue; // Skip, leave whatever the parent layer decided intact
                }

                if ($behavior === CapabilityBehavior::DISABLE) {
                    // Explicitly track that this was disabled (Useful for the UI Simulator)
                    $mergedCapabilities[$code] = [
                        'enforced'      => false,
                        'config'        => [],
                        'source_policy' => $profile->name,
                        'layer'         => $layerName,
                        'behavior'      => 'DISABLE'
                    ];
                }

                if ($behavior === CapabilityBehavior::ENFORCE) {
                    // If previously enforced, merge config. Otherwise, create new entry.
                    $existingConfig = $mergedCapabilities[$code]['config'] ?? [];
                    
                    $mergedCapabilities[$code] = [
                        'enforced'      => true,
                        'config'        => array_merge($existingConfig, $config),
                        'source_policy' => $profile->name,
                        'layer'         => $layerName,
                        'behavior'      => 'ENFORCE'
                    ];
                }
            }
        }

        self::$resolvedCache[$cacheKey] = $mergedCapabilities;
        return $mergedCapabilities;
    }

    /**
     * Identifies if this is an Asset Model, and extracts its Snipe-IT Category ID.
     */
    protected function resolveItemHierarchy(string $productType, int $productId): array
    {
        $basename = strtolower(class_basename($productType));
        
        $isModel = in_array($basename, ['assetmodel', 'asset_model']);
        $isCategory = in_array($basename, ['category']);
        
        $categoryId = null;

        if ($isCategory) {
            // If compiling directly for a Category target, the ID IS the category ID
            $categoryId = $productId;
        } elseif ($isModel) {
            $categoryId = DB::table('models')->where('id', $productId)->value('category_id');
        } else {
            $modelClass = Relation::getMorphedModel($productType) ?? $productType;
            if (class_exists($modelClass)) {
                $categoryId = DB::table((new $modelClass)->getTable())->where('id', $productId)->value('category_id');
            }
        }

        return [
            'is_model' => $isModel,
            'category_id' => $categoryId
        ];
    }

    /**
     * Safely fetches the active policy assignment for a specific scope/target.
     * Defensive: Accepts nullable IDs and immediately short-circuits to prevent type exceptions.
     */
    protected function getActiveAssignment(AssignmentScope $scope, ?int $scopeId, string $targetType, ?int $targetId)
    {
        if ($targetId === null) {
            return null;
        }

        $now = now();
        
        return ProfileAssignment::with('profile.capabilities')
            ->where('scope_level', $scope->value)
            ->when($scopeId !== null, fn($q) => $q->where('scope_id', $scopeId))
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->first();
    }
}