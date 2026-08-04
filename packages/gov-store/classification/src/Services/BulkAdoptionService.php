<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\CatalogNode;
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

    /**
     * Analyzes an array of UNSPSC codes and categorizes what action will be taken for a specific scope.
     */
    public function preview(array $codes, string $scopeType, int $scopeId): array
    {
        $nodes = CatalogNode::whereIn('code', $codes)->with('snipeMapping')->get();
        
        $summary = [
            'new'      => [], // Needs native Snipe-IT category creation & adoption
            'link'     => [], // Category exists, just needs adoption linkage
            'skipped'  => [], // Already adopted by this scope
        ];

        foreach ($nodes as $node) {
            $item = [
                'code'  => $node->code,
                'title' => $node->title_en,
            ];

            if (!$node->snipeMapping) {
                $summary['new'][] = $item;
                continue;
            }

            $categoryId = $node->snipeMapping->category_id;
            
            // Check if it's already adopted by this specific scope
            $isAdopted = DB::table('gov_tenant_scope_mappings')
                ->where('reference_type', 'category')
                ->where('reference_id', $categoryId)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('is_active', true)
                ->exists();

            if ($isAdopted) {
                $summary['skipped'][] = $item;
            } else {
                $summary['link'][] = $item;
            }
        }

        return $summary;
    }

    /**
     * Executes the bulk adoption securely within a database transaction.
     */
    public function execute(array $codes, string $scopeType, int $scopeId, int $userId): array
    {
        $preview = $this->preview($codes, $scopeType, $scopeId);
        $executed = 0;

        DB::transaction(function () use ($preview, $scopeType, $scopeId, $userId, &$executed) {
            
            // 1. Process 'New' items (Create native category + map + adopt)
            foreach ($preview['new'] as $item) {
                $this->creator->provisionAndMap(
                    $item['code'],
                    'consumable', // Defaulting bulk to consumable (can be configured later)
                    $scopeType,   // Governance matches the scope
                    $scopeType,
                    $scopeId,
                    $userId,
                    $item['title']
                );
                $executed++;
            }

            // 2. Process 'Link' items (Native category exists, just adopt it)
            foreach ($preview['link'] as $item) {
                $node = CatalogNode::with('snipeMapping')->where('code', $item['code'])->first();
                if ($node && $node->snipeMapping) {
                    $this->adoption->useCategory($node->snipeMapping->category_id, $scopeType, $scopeId);
                    $executed++;
                }
            }
        });

        return [
            'success' => true,
            'processed_count' => $executed,
            'skipped_count' => count($preview['skipped'])
        ];
    }
}