<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use Exception;
use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Models\CategoryGovernance;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ReferenceCreation;
use GovStore\TenantScope\Services\ReferenceOwnershipService;
use Illuminate\Support\Facades\DB;

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
        if ($targetScopeType !== 'global' && $targetScopeType !== 'national') {
            app(CatalogSourceEligibility::class)->target($targetScopeType, (int) $targetScopeId);
        } else {
            abort_unless(! app(TenantContext::class)->isActive
                || request()->attributes->get('gov_national_reviewed') === 'catalog.master.manage', 403);
        }

        return DB::transaction(function () use ($node, $name, $categoryType, $governanceType, $targetScopeType, $targetScopeId, $creatorUserId) {

            // 1. Defensively check if a Snipe-IT Category with this name already exists in the production DB
            // Retain category visibility checks when resolving an unmapped code.
            $mapping = DB::table('gov_catalog_snipe_mappings')->where('code', $node->code)->lockForUpdate()->first();
            $category = $mapping ? app(CatalogSourceEligibility::class)->category((int) $mapping->category_id)
                : Category::where('name', $name)->first();
            $created = ! $category;

            if ($category) {
                app(CatalogSourceEligibility::class)->category((int) $category->id);
                // Adoption can reuse the exact definition, never change its commodity.
                abort_unless(app(ProductTypeCommodity::class)->codeFor((int) $category->id) === $node->code, 409);
            }

            if (! $category) {
                // If missing, instantiate and save natively
                $category = new Category;
                $category->name = $name;
                $category->category_type = $categoryType;
                $category->checkin_email = 0;
                $category->require_acceptance = 0;
                $category->use_default_eula = 0;

                // Attempt to save, catching Snipe-IT's Watson validations
                if (! app(ReferenceCreation::class)->run(Category::class, fn () => $category->save())) {
                    $errors = $category->getErrors() ? $category->getErrors()->first() : 'Snipe-IT Category validation failed.';
                    throw new Exception('Validation Error: '.$errors);
                }
            }

            if (! $category->id) {
                throw new Exception('Critical Error: Mapped category has no ID.');
            }

            if ($category->category_type !== $categoryType) {
                throw new Exception('The existing category has a different inventory type.');
            }

            // 2. Map UNSPSC Code to the verified Category ID
            if ($created) {
                app(ProductTypeCommodity::class)->assign((int) $category->id, $node->code);
            }

            // 3. Write/Update Governance Metadata
            if ($created) {
                CategoryGovernance::firstOrCreate(
                    ['category_id' => $category->id],
                    [
                        'governance_type' => match ($targetScopeType) {
                            'location' => 'office', 'company' => 'company', default => 'national'
                        },
                        'created_by_company_id' => ($targetScopeType === 'company') ? $targetScopeId : null,
                        'created_by_user_id' => $creatorUserId,
                    ]
                );
            }

            if ($created) {
                app(ReferenceOwnershipService::class)->assign($category,
                    match ($targetScopeType) {
                        'location' => 'office', 'company' => 'company', default => 'national'
                    },
                    $targetScopeId, $creatorUserId, 'Created through scoped catalog provisioning.');
            }

            // 4. Auto-Adopt the category
            if (! in_array($governanceType, ['global', 'national'], true) && $targetScopeId) {
                $this->adoptionService->useCategory($category->id, $targetScopeType, $targetScopeId);
            }

            return $category;
        });
    }
}
