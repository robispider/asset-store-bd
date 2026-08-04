<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Models\CatalogCollection;
use App\Models\Category;
use Illuminate\Support\Collection;

class CatalogSearchService
{
    /**
     * Staged search utilizing SQL Case Relevance Scoring.
     */
    public function search(string $query, string $scheme = 'UNSPSC', int $limit = 30): Collection
    {
        $query = trim($query);
        if (empty($query)) {
            return collect();
        }

        $query_prefix = $query . '%';
        $query_contains = '%' . $query . '%';

        // Use the Eloquent builder for proper hydration and relationship loading
        $dbQuery = CatalogNode::where('scheme', $scheme);

        if (is_numeric($query)) {
            // Staged Numeric matching (Exact code -> Prefix range)
            $dbQuery->where(function($q) use ($query, $query_prefix) {
                $q->where('code', $query)
                  ->orWhere('code', 'LIKE', $query_prefix);
            })
            ->select('gov_catalog_nodes.*')
            ->selectRaw("
                (CASE 
                    WHEN code = ? THEN 100
                    WHEN code LIKE ? THEN 90
                    ELSE 50
                END) as relevance_score
            ", [$query, $query_prefix]);
        } else {
            // Staged Textual matching (Exact title -> Prefix -> Synonym -> Substring fallback)
            $dbQuery->where(function($q) use ($query, $query_prefix, $query_contains) {
                $q->where('title_en', 'LIKE', $query_prefix)
                  ->orWhere('title_en', 'LIKE', $query_contains)
                  ->orWhereHas('synonyms', function($syn) use ($query_contains) {
                      $syn->where('synonym', 'LIKE', $query_contains);
                  });
            })
            ->select('gov_catalog_nodes.*')
            ->selectRaw("
                (CASE 
                    WHEN title_en = ? THEN 80
                    WHEN title_en LIKE ? THEN 70
                    WHEN title_en LIKE ? THEN 50
                    ELSE 30
                END) as relevance_score
            ", [$query, $query_prefix, $query_contains]);
        }

        return $dbQuery->with(['definition', 'enrichment', 'synonyms', 'snipeMapping'])
            ->orderBy('relevance_score', 'desc')
            ->orderBy('code', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Universal Search: Returns results grouped by Collections, Master Catalog, and Local Inventory.
     */
    public function searchUniversal(string $query, int $locationId): array
    {
        $queryStr = trim($query);
        if (strlen($queryStr) < 2) {
            return [
                'collections' => collect(),
                'catalog'     => collect(),
                'local'       => collect()
            ];
        }

        $queryLike = '%' . $queryStr . '%';

        // 1. Search Collections
        $collections = CatalogCollection::where('name', 'LIKE', $queryLike)
            ->orWhere('description', 'LIKE', $queryLike)
            ->where('is_active', true)
            ->limit(5)
            ->get(['id', 'name', 'icon'])
            ->map(function ($c) {
                return [
                    'type' => 'collection', 
                    'id' => $c->id, 
                    'text' => $c->name, 
                    'icon' => $c->icon
                ];
            });

        // 2. Search Master Catalog (UNSPSC) - Reusing existing CQRS scoring logic
        $catalogNodes = $this->search($queryStr, 'UNSPSC', 10)->map(function ($n) {
            return [
                'type' => 'catalog', 
                'code' => $n->code, 
                'text' => $n->title_en, 
                'level' => $n->level, 
                'hid' => $n->hid
            ];
        });

        // 3. Search Local Adopted Inventory (Tenant Scoped!)
        // Implicitly applies TenantScope global isolation filters natively
        $localCategories = Category::where('name', 'LIKE', $queryLike)
            ->limit(5)
            ->get(['id', 'name', 'category_type']) 
            ->map(function ($cat) {
                return [
                    'type' => 'local', 
                    'id' => $cat->id, 
                    'text' => $cat->name, 
                    'cat_type' => $cat->category_type
                ];
            });

        return [
            'collections' => $collections,
            'catalog'     => $catalogNodes,
            'local'       => $localCategories
        ];
    }

    /**
     * Browse child items of a given parent node.
     */
    public function browse(?string $parentCode, string $scheme = 'UNSPSC'): Collection
    {
        return CatalogNode::where('scheme', $scheme)
            ->where('parent_code', $parentCode)
            ->orderBy('code', 'asc')
            ->get();
    }

    /**
     * Determine if a catalog node contains deeper sub-categories.
     */
    public function hasChildren(CatalogNode $node): bool
    {
        return CatalogNode::where('parent_code', $node->code)->exists();
    }

    /**
     * Resolve the ancestor breadcrumb trail using the materialized hid path.
     */
    public function ancestors(CatalogNode $node): Collection
    {
        // Splits "/10000000/10100000/10101500/" into array ['10000000', '10100000', '10101500']
        $codes = array_filter(explode('/', $node->hid));
        
        if (empty($codes)) {
            return collect();
        }

        return CatalogNode::whereIn('code', $codes)
            ->orderBy('level', 'asc')
            ->get();
    }

    /**
     * Fetch a single node with complete relationship trees loaded.
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
     * Get the full tree depth for a given node.
     * 
     * @param CatalogNode $node
     * @return int Depth level (1-4)
     */
    public function getDepth(CatalogNode $node): int
    {
        return $node->level;
    }

    /**
     * Resolve the ancestor breadcrumb trail using the materialized hid path.
     * Replaces the previous `ancestors` method.
     */
    public function getAncestorsByHid(string $hid): Collection
    {
        // Splits "/10000000/10100000/10101500/" into an array of codes
        $codes = array_filter(explode('/', $hid));
        
        if (empty($codes)) {
            return collect();
        }

        // Fetch all parent nodes in a single, indexed query
        return CatalogNode::whereIn('code', $codes)
            ->orderBy('level', 'asc')
            ->get();
    }

    /**
     * Get the immediate sibling nodes of a given node.
     */
    public function getSiblings(CatalogNode $node): Collection
    {
        // No parent means no siblings
        if (!$node->parent_code) {
            return collect();
        }

        return CatalogNode::where('parent_code', $node->parent_code)
            ->where('code', '!=', $node->code) // Exclude self
            ->orderBy('code', 'asc')
            ->limit(10) // Limit to a reasonable number for UI display
            ->get();
    }
}