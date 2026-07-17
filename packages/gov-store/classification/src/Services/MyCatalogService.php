<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class MyCatalogService
{
    public function getLocalGrid(int $companyId, int $perPage = 50): LengthAwarePaginator
    {
        // Disable global scopes to bypass the TenantScope hiding our archived categories inside this dashboard!
        return Category::withoutGlobalScopes()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            ->join('gov_tenant_scope_mappings as usage', function ($join) use ($companyId) {
                $join->on('categories.id', '=', 'usage.reference_id')
                     ->where('usage.reference_type', '=', 'category')
                     ->where('usage.scope_type', '=', 'company')
                     ->where('usage.scope_id', '=', $companyId);
            })
            ->leftJoin('gov_category_governance as gov', 'categories.id', '=', 'gov.category_id')
            ->leftJoin('companies as origin_company', 'gov.created_by_company_id', '=', 'origin_company.id')
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect(
                'usage.updated_at as adopted_at',
                'usage.is_active as is_adopted_active', // Fixed: Fetch active state of mapping
                'gov.governance_type',
                'origin_company.name as owner_name',
                'map.code as unspsc_code'
            )
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }

    public function getLocalDetails(int $categoryId, int $companyId, int $locationId)
    {
        // Bypass scopes to retrieve details of archived items safely
        $category = Category::withoutGlobalScopes()->findOrFail($categoryId);

        $adoption = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', 'company')
            ->where('scope_id', $companyId)
            ->first();

        if (!$adoption) {
            return null;
        }

        $governance = DB::table('gov_category_governance as gov')
            ->leftJoin('companies', 'gov.created_by_company_id', '=', 'companies.id')
            ->where('gov.category_id', $categoryId)
            ->select('gov.governance_type', 'companies.name as company_name')
            ->first();

        $stats = [
            'assets'      => DB::table('assets')->where('company_id', $companyId)->where('location_id', $locationId)
                             ->whereIn('model_id', function ($query) use ($categoryId) {
                                $query->select('id')->from('models')->where('category_id', $categoryId)->whereNull('deleted_at');
                             })->whereNull('deleted_at')->count(),
            'consumables' => DB::table('consumables')->where('company_id', $companyId)->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'accessories' => DB::table('accessories')->where('company_id', $companyId)->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'components'  => DB::table('components')->where('company_id', $companyId)->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'licenses'    => DB::table('licenses')->where('company_id', $companyId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
        ];

        return [
            'category'   => $category,
            'adoption'   => $adoption,
            'governance' => $governance,
            'stats'      => $stats
        ];
    }


    /**
     * Retrieve all Globally Available Categories (Implicit shared standards with zero company scoping restrictions).
     */
    public function getGlobalStandardsGrid(int $perPage = 50): LengthAwarePaginator
    {
        return Category::query()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            // Filter out any categories that have been mapped to specific company scopes
            ->whereNotIn('categories.id', function ($query) {
                $query->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', 'category');
            })
            // Left join mapping to get UNSPSC code
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect('map.code as unspsc_code')
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }

}