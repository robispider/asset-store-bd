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
     * Adopts a category for a specific company.
     * Inserts into the foundational Tenant Scoping table to make the category visible.
     */
    public function useCategory(int $categoryId, int $companyId): void
    {
        DB::table('gov_tenant_scope_mappings')->updateOrInsert(
            [
                'reference_type' => 'category',
                'reference_id'   => $categoryId,
                'scope_type'     => 'company',
                'scope_id'       => $companyId,
            ],
            ['updated_at' => now()]
        );
    }

    /**
     * Removes a category from a company's operational catalog.
     * Enforces strict governance checks across all 5 Snipe-IT item types before removal.
     */
    public function stopUsingCategory(int $categoryId, int $companyId): void
    {
        if ($this->hasActiveReferences($categoryId, $companyId)) {
            throw new Exception("Governance Violation: Cannot abandon this category. Your organization currently owns active items mapped to it.");
        }

        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->where('scope_id', $companyId)
            ->delete();
    }

    /**
     * Checks if a specific company has explicitly adopted a category.
     */
    public function isUsedBy(int $categoryId, int $companyId): bool
    {
        return DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->where('scope_id', $companyId)
            ->exists();
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

    /**
     * Internal Governance Guard: Validates whether the company owns active records
     * assigned to the specified category across all polymorphic Snipe-IT structures.
     */
    protected function hasActiveReferences(int $categoryId, int $companyId): bool
    {
        // 1. Hardware Assets (Assets link to Models, Models link to Categories)
        $hasAssets = Asset::where('company_id', $companyId)
            ->whereHas('model', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })->exists();

        if ($hasAssets) return true;

        // 2. Accessories
        if (Accessory::where('company_id', $companyId)->where('category_id', $categoryId)->exists()) return true;

        // 3. Consumables
        if (Consumable::where('company_id', $companyId)->where('category_id', $categoryId)->exists()) return true;

        // 4. Components
        if (Component::where('company_id', $companyId)->where('category_id', $categoryId)->exists()) return true;

        // 5. Licenses
        if (License::where('company_id', $companyId)->where('category_id', $categoryId)->exists()) return true;

        return false;
    }

    /**
     * Operationally soft-archives an adopted category (Hides it from creation menus).
     */
    public function archiveCategory(int $categoryId, int $companyId): void
    {
        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->where('scope_id', $companyId)
            ->update(['is_active' => false]);
    }

    /**
     * Operationally restores an archived adopted category.
     */
    public function restoreCategory(int $categoryId, int $companyId): void
    {
        DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->where('scope_id', $companyId)
            ->update(['is_active' => true]);
    }
}