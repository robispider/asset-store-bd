<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class MyCatalogService
{
    /**
     * Retrieve the grid of operational categories adopted by the active context (company or location).
     */
   public function getLocalGrid(int $companyId, int $locationId, int $perPage = 50): LengthAwarePaginator
    {
        return Category::withoutGlobalScopes()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            // Inner Join: Categories adopted by the Company OR the specific Location
            ->join('gov_tenant_scope_mappings as usage', function ($join) use ($companyId, $locationId) {
                $join->on('categories.id', '=', 'usage.reference_id')
                     ->where('usage.reference_type', '=', 'category')
                     ->where(function ($query) use ($companyId, $locationId) {
                         if ($companyId > 0) {
                             $query->where(function ($sq) use ($companyId) {
                                 $sq->where('usage.scope_type', 'company')->where('usage.scope_id', $companyId);
                             });
                         }
                         if ($locationId > 0) {
                             $query->orWhere(function ($sq) use ($locationId) {
                                 $sq->where('usage.scope_type', 'location')->where('usage.scope_id', $locationId);
                             });
                         }
                     });
            })
            ->leftJoin('gov_category_governance as gov', 'categories.id', '=', 'gov.category_id')
            ->leftJoin('companies as origin_company', 'gov.created_by_company_id', '=', 'origin_company.id')
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect(
                'usage.updated_at as adopted_at',
                'usage.is_active as is_adopted_active',
                'usage.scope_type as active_adoption_scope', // Identifies if it was adopted via Company or Location
                'gov.governance_type',
                'origin_company.name as owner_name',
                'map.code as unspsc_code'
            )
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get detailed analytics for a single category scoped specifically to the active context.
     */
    public function getLocalDetails(int $categoryId, string $scopeType, int $scopeId, int $locationId)
    {
        $category = Category::withoutGlobalScopes()->findOrFail($categoryId);

        // Verify adoption against the active scope
        $adoption = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if (!$adoption) {
            return null; // Category not adopted by this scope
        }

        $governance = DB::table('gov_category_governance as gov')
            ->leftJoin('companies', 'gov.created_by_company_id', '=', 'companies.id')
            ->where('gov.category_id', $categoryId)
            ->select('gov.governance_type', 'companies.name as company_name')
            ->first();

        // Localized physical usage counts (Always filtered by the physical active location)
        $stats = [
            'assets'      => DB::table('assets')->where('location_id', $locationId)
                             ->whereIn('model_id', function ($query) use ($categoryId) {
                                $query->select('id')->from('models')->where('category_id', $categoryId)->whereNull('deleted_at');
                             })->whereNull('deleted_at')->count(),
            'consumables' => DB::table('consumables')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'accessories' => DB::table('accessories')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'components'  => DB::table('components')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'licenses'    => DB::table('licenses')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
        ];

        return [
            'category'   => $category,
            'adoption'   => $adoption,
            'governance' => $governance,
            'stats'      => $stats,
            'scopeNoun'  => ($scopeType === 'company') ? 'organization' : 'office location'
        ];
    }

    /**
     * Retrieve all Globally Available Categories (Implicit shared standards with zero scoping restrictions).
     */
    public function getGlobalStandardsGrid(int $perPage = 50): LengthAwarePaginator
    {
        return Category::query()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            ->whereNotIn('categories.id', function ($query) {
                $query->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', 'category');
            })
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect('map.code as unspsc_code')
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }
}