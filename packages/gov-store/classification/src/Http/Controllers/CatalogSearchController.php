<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\CatalogSearchService;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\Classification\Models\CategoryGovernance;
use GovStore\TenantScope\Contexts\TenantContext;

class CatalogSearchController extends Controller
{
    /**
     * Display the human-centered search UI.
     */
    public function index(Request $request, CatalogSearchService $searcher)
    {
        $query = $request->input('q');
        $results = collect();

        if ($query) {
            $results = $searcher->search($query);
        }

        return view('gov-classification::search.index', compact('query', 'results'));
    }

    /**
     * AJAX: Search nodes, now including the hid for breadcrumb rendering.
     */
    public function searchAjax(Request $request, CatalogSearchService $searcher)
    {
        $query = $request->input('q', '');
        $scheme = $request->input('scheme', 'UNSPSC');

        $results = $searcher->search($query, $scheme);

        return response()->json([
            'results' => $results->map(function ($node) {
                return [
                    'id'          => $node->id,
                    'code'        => $node->code,
                    'text'        => $node->title_en,
                    'level'       => $node->level,
                    'scheme'      => $node->scheme,
                    'version'     => $node->version,
                    'hid'         => $node->hid,
                    'has_mapping' => (bool) $node->snipeMapping,
                ];
            }),
        ]);
    }

    /**
     * AJAX: Get Contextual Hierarchy (Ancestors & Siblings) for a node.
     */
    public function contextAjax(Request $request, CatalogSearchService $searcher)
    {
        $code = $request->input('code');
        $scheme = $request->input('scheme', 'UNSPSC');

        $node = $searcher->findByCode($scheme, $code);

        if (!$node) {
            return response()->json([], 404);
        }

        return response()->json([
            'ancestors' => $searcher->getAncestorsByHid($node->hid),
            'siblings'  => $searcher->getSiblings($node),
        ]);
    }

    /**
     * AJAX: Browse children of a node.
     */
    public function browseAjax(Request $request, CatalogSearchService $searcher)
    {
        $parentCode = $request->input('parent_code');
        $scheme = $request->input('scheme', 'UNSPSC');

        $nodes = $searcher->browse($parentCode, $scheme);

        return response()->json([
            'results' => $nodes->map(function ($node) use ($searcher) {
                return [
                    'id'         => $node->id,
                    'code'       => $node->code,
                    'text'       => "[{$node->code}] {$node->title_en}",
                    'children'   => $searcher->hasChildren($node),
                    'level'      => $node->level,
                    'scheme'     => $node->scheme,
                ];
            }),
        ]);
    }

    /**
     * AJAX: Get ancestors (breadcrumb path) for a node.
     */
    public function ancestorsAjax(Request $request, CatalogSearchService $searcher)
    {
        $code = $request->input('code');
        $scheme = $request->input('scheme', 'UNSPSC');

        $node = $searcher->findByCode($scheme, $code);

        if (!$node) {
            return response()->json(['ancestors' => collect()]);
        }

        $ancestors = $searcher->ancestors($node);

        return response()->json([
            'ancestors' => $ancestors->map(function ($ancestor) {
                return [
                    'id'   => $ancestor->id,
                    'code' => $ancestor->code,
                    'text' => "[{$ancestor->code}] {$ancestor->title_en}",
                ];
            }),
        ]);
    }

    /**
     * Show the mapping editor and governance/adoption metadata. (Optimized & Clean)
     */
    public function showMapping(Request $request, $code = null)
    {
        $code = $code ?: $request->input('code');
        $scheme = $request->input('scheme', 'UNSPSC');

        if (empty($code)) {
            $mappings = \GovStore\Classification\Models\CatalogNode::whereHas('snipeMapping')
                ->with(['snipeMapping'])
                ->paginate(15);

            return view('gov-classification::manager.mapping', compact('mappings'));
        }

        $searcher = app(CatalogSearchService::class);
        $node = $searcher->findByCode($scheme, (string) $code);

        if (!$node) {
            return response()->json(['success' => false, 'message' => 'Node not found.'], 404);
        }

           $tenantContext = app(TenantContext::class);
        $adoptionService = app(CategoryAdoptionService::class);
        
        $isAdoptedByMe = false;
        $activeScopeType = 'location';
        $activeScopeId = $tenantContext->locationId;

        // If they have a parent company, operations upgrade to the company scope
        if ($tenantContext->companyId > 0) {
            $activeScopeType = 'company';
            $activeScopeId = $tenantContext->companyId;
        }
        
        $governance = null;
        
        if ($node->snipeMapping) {
            $categoryId = $node->snipeMapping->category_id;
            
            if ($activeScopeId > 0) {
                $isAdoptedByMe = $adoptionService->isUsedBy($categoryId, $activeScopeType, $activeScopeId);
            }
            
            $governance = \GovStore\Classification\Models\CategoryGovernance::with('originatingCompany')
                ->where('category_id', $categoryId)->first();
        }

        return view('gov-classification::search.mapping', [
            'node'            => $node,
            'currentMapping'  => $node->snipeMapping,
            'isAdoptedByMe'   => $isAdoptedByMe,
            'governance'      => $governance,
            'activeScopeType' => $activeScopeType,
        ]);
    }

    /**
     * AJAX: Search native Snipe-IT Category catalog for Select2 autocomplete.
     */
    public function searchSnipeCategories(Request $request)
    {
        $query = $request->input('q');
        
        $categories = \App\Models\Category::where('name', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json([
            'results' => $categories->map(function ($cat) {
                return [
                    'id'   => $cat->id,
                    'text' => $cat->name
                ];
            })
        ]);
    }

    /**
     * POST: Persist the linked classification mapping safely.
     */
    public function saveMapping(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'category_id' => 'required|integer'
        ]);

        try {
            $code = $request->input('code');
            $categoryId = $request->input('category_id');

            // Secure idempotent upsert using DB builder
            \Illuminate\Support\Facades\DB::table('gov_catalog_snipe_mappings')->updateOrInsert(
                ['code' => $code],
                [
                    'category_id' => $categoryId,
                    'updated_at'  => now()
                ]
            );

            $category = \App\Models\Category::find($categoryId);

            return response()->json([
                'success'       => true,
                'category_name' => $category ? $category->name : 'Unresolved'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save mapping: ' . $e->getMessage()
            ], 500);
        }
    }
}