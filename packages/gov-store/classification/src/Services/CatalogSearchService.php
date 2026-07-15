<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\CatalogNode;
use Illuminate\Support\Collection;

class CatalogSearchService
{
    /**
     * Human-centered natural language search across titles, synonyms, and translations.
     * Powers both the Admin UI and the Storekeeper Dropdowns.
     * 
     * @param string $query The search query
     * @param string $scheme The classification scheme (UNSPSC, CGA, etc.)
     * @param int $limit Maximum results to return
     * @return Collection<CatalogNode> Eager loaded with all relations needed for UI
     */
    public function search(string $query, string $scheme = 'UNSPSC', int $limit = 20): Collection
    {
        if (empty(trim($query))) {
            return collect();
        }

        $query = trim($query);

        return CatalogNode::where('scheme', $scheme)
            ->selectable()
            ->where(function ($q) use ($query) {
                // Exact/Partial Code match
                $q->where('code', 'LIKE', "{$query}%")
                  // English Reference Title
                  ->orWhere('title_en', 'LIKE', "%{$query}%")
                  // Operational Bangla Title (enrichment)
                  ->orWhereHas('enrichment', function ($en) use ($query) {
                      $en->where('title_bn', 'LIKE', "%{$query}%");
                  })
                  // Operational Synonyms (e.g. "Laptop", "গরু")
                  ->orWhereHas('synonyms', function ($syn) use ($query) {
                      $syn->where('synonym', 'LIKE', "%{$query}%");
                  });
            })
            // Eager load everything needed for the UI
            ->with(['definition', 'enrichment', 'synonyms', 'snipeMapping'])
            ->limit($limit)
            ->get();
    }

    /**
     * Browse direct children of a parent code.
     * 
     * @param string|null $parentCode The parent node code (null for root-level)
     * @param string $scheme The classification scheme
     * @return Collection<CatalogNode>
     */
    public function browse(?string $parentCode = null, string $scheme = 'UNSPSC'): Collection
    {
        if ($parentCode === null) {
            return CatalogNode::where('scheme', $scheme)
                ->byLevel(CatalogNode::LEVEL_SEGMENT)
                ->orderBy('code')
                ->selectable()
                ->get();
        }

        return CatalogNode::where('scheme', $scheme)
            ->where('parent_code', $parentCode)
            ->orderBy('code')
            ->selectable()
            ->get();
    }

    /**
     * Get all ancestors for a node by traversing parent_code chain.
     * 
     * @param CatalogNode $node The node to find ancestors for
     * @return Collection<CatalogNode> Ordered by level ascending
     */
    public function ancestors(CatalogNode $node): Collection
    {
        if (empty($node->hid)) {
            return collect();
        }

        // Split HID materialized path and retrieve all ancestors
        $codes = array_filter(explode('/', trim($node->hid, '/')));

        if (empty($codes)) {
            return collect();
        }

        return CatalogNode::whereIn('code', $codes)
            ->orderBy('level', 'asc')
            ->get();
    }

    /**
     * Get a single node by its scheme/code identity.
     * 
     * @param string $scheme The classification scheme
     * @param string $code The node code
     * @return CatalogNode|null
     */
    public function findByCode(string $scheme, string $code): ?CatalogNode
    {
        return CatalogNode::where('scheme', $scheme)
            ->where('code', $code)
            ->with(['definition', 'enrichment', 'synonyms', 'snipeMapping'])
            ->first();
    }

    /**
     * Get all top-level (root/segment) nodes for a scheme.
     * 
     * @param string $scheme The classification scheme
     * @return Collection<CatalogNode>
     */
    public function getRoots(string $scheme = 'UNSPSC'): Collection
    {
        return CatalogNode::where('scheme', $scheme)
            ->byLevel(CatalogNode::LEVEL_SEGMENT)
            ->orderBy('code')
            ->selectable()
            ->get();
    }

    /**
     * Check if a node has children.
     * 
     * @param CatalogNode $node
     * @return bool
     */
    public function hasChildren(CatalogNode $node): bool
    {
        return CatalogNode::where('parent_code', $node->code)->exists();
    }

    /**
     * Get the full tree depth for a given node.
     * 
     * @param CatalogNode $node
     * @return int Depth level (1-4)
     */
    public function getDepth(CatalogNode $node): int
    {
        return $node->level;
    }
}
