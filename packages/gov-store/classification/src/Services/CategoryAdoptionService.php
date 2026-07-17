<?php

namespace GovStore\Classification\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;
use App\Models\Component;
use App\Models\License;
use Exception;

class CategoryAdoptionService
{
    /**
     * Adopts a category using dynamic scoping (either company or location).
     */
    public function useCategory(int $categoryId, string $scopeType, int $scopeId): void
    {
        if (!in_array($scopeType, ['company', 'location'])) {
            throw new Exception("Invalid adoption scope type.");
        }

        DB::table('gov_tenant_scope_mappings')->updateOrInsert(
            [
                'reference_type' => 'category',
                'reference_id'   => $categoryId,
                'scope_type'     => $scopeType,
                'scope_id'       => $scopeId,
            ],
            ['updated_at' => now(), 'is_active' => true]
        );
    }

    public function stopUsingCategory(int $categoryId, string $scopeType, int $scopeId): void
    {
        if ($this->hasActiveReferences($categoryId, $scopeType, $scopeId)) {
            throw new Exception("Governance Violation: Cannot abandon this category. Your office/organization currently owns active items mapped to it.");
        }

        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete();
    }

    public function archiveCategory(int $categoryId, string $scopeType, int $scopeId): void
    {
        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->update(['is_active' => false]);
    }

    public function restoreCategory(int $categoryId, string $scopeType, int $scopeId): void
    {
        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->update(['is_active' => true]);
    }

    public function isUsedBy(int $categoryId, string $scopeType, int $scopeId): bool
    {
        return DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->exists();
    }

    protected function hasActiveReferences(int $categoryId, string $scopeType, int $scopeId): bool
    {
        $column = ($scopeType === 'company') ? 'company_id' : 'location_id';

        $hasAssets = Asset::where($column, $scopeId)
            ->whereHas('model', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })->exists();
            
        if ($hasAssets) return true;

        if (Consumable::where($column, $scopeId)->where('category_id', $categoryId)->exists()) return true;
        if (Accessory::where($column, $scopeId)->where('category_id', $categoryId)->exists()) return true;
        if (Component::where($column, $scopeId)->where('category_id', $categoryId)->exists()) return true;
        
        // Licenses typically do not use location_id natively in Snipe-IT, but safe fallback
        if (License::where($column, $scopeId)->where('category_id', $categoryId)->exists()) return true;

        return false;
    }
    /**
     * Retrieves the count of companies actively using this category.
     */
    public function usageCount(int $categoryId): int
    {
        return DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->count();
    }

 
}