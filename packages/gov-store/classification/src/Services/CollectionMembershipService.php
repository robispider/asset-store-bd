<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Models\CatalogCollectionNode;
use Illuminate\Support\Facades\DB;

class CollectionMembershipService
{
    /**
     * Expands any folder codes (level 1-3) in the array into their respective level 4 commodities.
     */
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
     * Bulk attaches nodes (and expanded folder members) to a curation collection.
     */
    public function addNodesToCollection(int $collectionId, array $codes): array
    {
        $flatCodes = $this->expandFolders($codes);
        $inserted = 0;

        DB::transaction(function () use ($collectionId, $flatCodes, &$inserted) {
            foreach ($flatCodes as $code) {
                $node = CatalogCollectionNode::firstOrCreate([
                    'collection_id' => $collectionId,
                    'code' => $code
                ]);

                if ($node->wasRecentlyCreated) {
                    $inserted++;
                }
            }
        });

        return [
            'success' => true,
            'added_count' => $inserted,
            'total_requested' => count($codes),
            'total_expanded' => count($flatCodes)
        ];
    }
}