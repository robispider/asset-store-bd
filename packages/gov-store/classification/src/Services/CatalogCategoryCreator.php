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
        ?int $targetCompanyId, 
        int $creatorUserId, 
        ?string $customName = null
    ): Category {
        $node = CatalogNode::where('code', $unspscCode)->firstOrFail();
        $name = $customName ?: $node->title_en;

        return DB::transaction(function () use ($node, $name, $categoryType, $governanceType, $targetCompanyId, $creatorUserId) {
            
            // 1. Create Core Snipe-IT Category
            $category = Category::create([
                'name' => $name,
                'category_type' => $categoryType,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
            ]);

            // 2. Map UNSPSC Code to Snipe-IT Category
            DB::table('gov_catalog_snipe_mappings')->updateOrInsert(
                ['code' => $node->code],
                ['category_id' => $category->id, 'updated_at' => now()]
            );

            // 3. Write Governance Metadata
            \GovStore\Classification\Models\CategoryGovernance::create([
                'category_id'           => $category->id,
                'governance_type'       => $governanceType,
                'created_by_company_id' => $targetCompanyId,
                'created_by_user_id'    => $creatorUserId,
            ]);

            // 4. Auto-Adopt if scoped to a specific company
            if ($governanceType === 'company' && $targetCompanyId) {
                $this->adoptionService->useCategory($category->id, $targetCompanyId);
            }

            return $category;
        });
    }
}