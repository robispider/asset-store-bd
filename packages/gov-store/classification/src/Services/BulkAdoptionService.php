<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Models\CatalogCollectionNode;
use Illuminate\Support\Facades\DB;
use Exception;

class BulkAdoptionService
{
    protected CatalogCategoryCreator $creator;
    protected CategoryAdoptionService $adoption;

    public function __construct(CatalogCategoryCreator $creator, CategoryAdoptionService $adoption)
    {
        $this->creator = $creator;
        $this->adoption = $adoption;
    }

    protected function expandFolders(array $codes): array
    {
        $nodes = CatalogNode::whereIn('code', $codes)->get();
        $expandedCodes = [];

        foreach ($nodes as $node) {
            if ($node->level === 4) {
                $expandedCodes[] = $node->code;
            } else {
                $childCodes = CatalogNode::where('hid', 'LIKE', $node->hid . '%')
                    ->where('level', 4)
                    ->pluck('code')
                    ->toArray();
                
                $expandedCodes = array_merge($expandedCodes, $childCodes);
            }
        }

        return array_unique($expandedCodes);
    }

    /**
     * Preview mapping with pre-existing category types populated.
     */
    public function preview(array $codes, string $scopeType, ?int $scopeId, ?int $companyId = null, ?int $locationId = null, ?int $collectionId = null): array
    {
        $flatCodes = $this->expandFolders($codes);
        $nodes = CatalogNode::whereIn('code', $flatCodes)->with(['snipeMapping.category', 'enrichment'])->get();
        $defaults = $collectionId === null ? collect() : CatalogCollectionNode::where('collection_id', $collectionId)
            ->whereIn('code', $flatCodes)->pluck('category_type', 'code');
        // Master commodity classification only: do not expose another office's category details or stock.
        $mappedTypes = DB::table('gov_catalog_snipe_mappings as mapping')->join('categories as category', 'category.id', '=', 'mapping.category_id')
            ->whereIn('mapping.code', $flatCodes)->whereNull('category.deleted_at')->pluck('category.category_type', 'mapping.code');
        
        $summary = [
            'new'      => [], 
            'link'     => [], 
            'skipped'  => [], 
        ];

        foreach ($nodes as $node) {
            $item = [
                'code'          => $node->code,
                'title'         => app()->getLocale() === 'bn-BD' ? ($node->enrichment?->title_bn ?: $node->title_en) : $node->title_en,
                'category_type' => $defaults[$node->code] ?? 'consumable',
            ];

            if (!$node->snipeMapping) {
                $summary['new'][] = $item;
                continue;
            }

            $categoryId = $node->snipeMapping->category_id;
            
            // Populate the pre-existing category type so the UI dropdown can display it accurately
            if (isset($mappedTypes[$node->code])) {
                $item['category_type'] = $mappedTypes[$node->code];
            }

            // Tier 1 Check
            $isGlobal = DB::table('gov_category_governance')
                ->where('category_id', $categoryId)
                ->whereIn('governance_type', ['global', 'national'])
                ->exists();

            if ($isGlobal) {
                $summary['skipped'][] = $item;
                continue;
            }

            // Tier 2 Check
            if ($companyId > 0) {
                $isCompanyAdopted = DB::table('gov_tenant_scope_mappings')
                    ->where('reference_type', 'category')
                    ->where('reference_id', $categoryId)
                    ->where('scope_type', 'company')
                    ->where('scope_id', $companyId)
                    ->where('is_active', true)
                    ->exists();

                if ($isCompanyAdopted) {
                    $summary['skipped'][] = $item;
                    continue;
                }
            }

            // Tier 3 Check
            if ($scopeType === 'location' && $locationId > 0) {
                $isLocationAdopted = DB::table('gov_tenant_scope_mappings')
                    ->where('reference_type', 'category')
                    ->where('reference_id', $categoryId)
                    ->where('scope_type', 'location')
                    ->where('scope_id', $locationId)
                    ->where('is_active', true)
                    ->exists();

                if ($isLocationAdopted) {
                    $summary['skipped'][] = $item;
                    continue;
                }
            }

            $summary['link'][] = $item;
        }

        return $summary;
    }

    /**
     * Executes bulk adoptions using custom types chosen by the user.
     */
    public function execute(array $items, string $scopeType, ?int $scopeId, int $userId): array
    {
        $executed = 0;
        $skipped = 0;

        DB::transaction(function () use ($items, $scopeType, $scopeId, $userId, &$executed, &$skipped) {
            foreach ($items as $item) {
                $code = $item['code'];
                $selectedType = $item['category_type'];

                $node = CatalogNode::where('code', $code)->with('snipeMapping')->first();
                if (!$node) {
                    $skipped++;
                    continue;
                }

                if (!$node->snipeMapping) {
                    // Create the category natively with the specific user-selected type
                    $this->creator->provisionAndMap(
                        $code,
                        $selectedType, 
                        $scopeType,   
                        $scopeType,
                        $scopeId,
                        $userId,
                        $node->title_en
                    );
                    $executed++;
                } else {
                    app(CatalogSourceEligibility::class)->category((int) $node->snipeMapping->category_id);
                    if (in_array($scopeType, ['global', 'national'], true)) {
                        $skipped++;
                        continue;
                    }
                    // Link standard adoption (Use existing category_id)
                    $this->adoption->useCategory($node->snipeMapping->category_id, $scopeType, $scopeId);
                    $executed++;
                }
            }
        });

        return [
            'success' => true,
            'processed_count' => $executed,
            'skipped_count' => $skipped
        ];
    }
}
