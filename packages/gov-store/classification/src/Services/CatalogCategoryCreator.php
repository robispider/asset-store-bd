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

    /**
     * Orchestrates the 1-Click Provisioning Workflow with self-healing duplicate resolution.
     */
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
            
            // 1. Defensively check if a Snipe-IT Category with this name already exists in the production DB
            $category = Category::where('name', $name)->first();

            if (!$category) {
                // If missing, instantiate and save natively
                $category = new Category();
                $category->name = $name;
                $category->category_type = $categoryType;
                $category->checkin_email = 0;
                $category->require_acceptance = 0;
                $category->use_default_eula = 0;

                // Attempt to save, catching Snipe-IT's Watson validations
                if (!$category->save()) {
                    $errors = $category->getErrors() ? $category->getErrors()->first() : 'Snipe-IT Category validation failed.';
                    throw new Exception("Validation Error: " . $errors);
                }
            }

            if (!$category->id) {
                throw new Exception("Critical Error: Mapped category has no ID.");
            }

            // 2. Map UNSPSC Code to the verified Category ID
            DB::table('gov_catalog_snipe_mappings')->updateOrInsert(
                ['code' => $node->code],
                ['category_id' => $category->id, 'updated_at' => now()]
            );

            // 3. Write/Update Governance Metadata
            \GovStore\Classification\Models\CategoryGovernance::updateOrCreate(
                ['category_id' => $category->id],
                [
                    'governance_type'       => $governanceType,
                    'created_by_company_id' => ($targetScopeType === 'company') ? $targetScopeId : null,
                    'created_by_user_id'    => $creatorUserId,
                ]
            );

            // 4. Auto-Adopt the category
            if ($governanceType !== 'global' && $targetScopeId) {
                $this->adoptionService->useCategory($category->id, $targetScopeType, $targetScopeId);
            }

            return $category;
        });
    }
}