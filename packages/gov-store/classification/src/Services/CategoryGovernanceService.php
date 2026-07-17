<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryGovernanceService
{
    /**
     * Retrieve the master grid of all operational categories mapped via GovStore.
     */
    public function getMasterGrid(int $perPage = 50): LengthAwarePaginator
    {
        return Category::query()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            // Left join Governance metadata
            ->leftJoin('gov_category_governance as gov', 'categories.id', '=', 'gov.category_id')
            ->leftJoin('companies as origin_company', 'gov.created_by_company_id', '=', 'origin_company.id')
            // Left join mapping to get UNSPSC code
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect(
                'gov.governance_type',
                'origin_company.name as owner_name',
                'map.code as unspsc_code'
            )
            // Subquery: Count total adoptions across the entire platform
            ->addSelect(['adoption_count' => DB::table('gov_tenant_scope_mappings')
                ->selectRaw('count(*)')
                ->whereColumn('reference_id', 'categories.id')
                ->where('reference_type', 'category')
            ])
            // Subquery: Count physical asset models globally using this category
            ->addSelect(['models_count' => DB::table('models')
                ->selectRaw('count(*)')
                ->whereColumn('category_id', 'categories.id')
                ->whereNull('deleted_at')
            ])
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get detailed analytics for a single operational category.
     */
    public function getCategoryDetails(int $categoryId)
    {
        $category = Category::findOrFail($categoryId);

        $governance = DB::table('gov_category_governance as gov')
            ->leftJoin('companies', 'gov.created_by_company_id', '=', 'companies.id')
            ->leftJoin('users', 'gov.created_by_user_id', '=', 'users.id')
            ->where('gov.category_id', $categoryId)
            ->select(
                'gov.governance_type',
                'gov.created_at',
                'companies.name as company_name',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name'
            )
            ->first();

        $mapping = DB::table('gov_catalog_snipe_mappings as map')
            ->leftJoin('gov_catalog_nodes as node', 'map.code', '=', 'node.code')
            ->where('map.category_id', $categoryId)
            ->select('node.code', 'node.title_en', 'node.hid')
            ->first();

        $stats = [
            'adoptions'   => DB::table('gov_tenant_scope_mappings')->where('reference_type', 'category')->where('reference_id', $categoryId)->count(),
            'assets'      => DB::table('assets')->whereIn('model_id', function ($query) use ($categoryId) {
                                $query->select('id')->from('models')->where('category_id', $categoryId)->whereNull('deleted_at');
                             })->whereNull('deleted_at')->count(),
            'models'      => DB::table('models')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'consumables' => DB::table('consumables')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'accessories' => DB::table('accessories')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'components'  => DB::table('components')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'licenses'    => DB::table('licenses')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
        ];

        return [
            'category'   => $category,
            'governance' => $governance,
            'mapping'    => $mapping,
            'stats'      => $stats
        ];
    }
}