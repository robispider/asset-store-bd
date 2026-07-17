<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use GovStore\Classification\Models\CatalogNode;
use Illuminate\Support\Facades\DB;
use Exception;

class CatalogCategoryCreator
{
    protected CategoryAdoptionService $adoptionService;

    public function __construct(CategoryAdoptionService $adoptionService)
    {
        $this->adoptionService = $adoptionService;
    }

 public function provisionAndMap(
        string $unspscCode, 
        string $categoryType, 
        string $governanceType, 
        string $targetScopeType, 
        ?int $targetScopeId, 
        int $creatorUserId, 
        ?string $customName = null
    ): Category {
        $node = CatalogNode::where('code', $unspscCode)->firstOrFail();
        $name = $customName ?: $node->title_en;

        return DB::transaction(function () use ($node, $name, $categoryType, $governanceType, $targetScopeType, $targetScopeId, $creatorUserId) {
            
            $category = Category::create([
                'name' => $name,
                'category_type' => $categoryType,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
            ]);

            DB::table('gov_catalog_snipe_mappings')->updateOrInsert(
                ['code' => $node->code],
                ['category_id' => $category->id, 'updated_at' => now()]
            );

            // Governance type can be 'global', 'company', or 'location'
            \GovStore\Classification\Models\CategoryGovernance::create([
                'category_id'           => $category->id,
                'governance_type'       => $governanceType, 
                'created_by_company_id' => ($targetScopeType === 'company') ? $targetScopeId : null,
                'created_by_user_id'    => $creatorUserId,
            ]);

            // Auto-Adopt using the correct dynamic scope
            if ($governanceType !== 'global' && $targetScopeId) {
                $this->adoptionService->useCategory($category->id, $targetScopeType, $targetScopeId);
            }

            return $category;
        });
    }
}