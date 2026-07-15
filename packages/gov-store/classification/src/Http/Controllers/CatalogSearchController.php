<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\CatalogSearchService;

class CatalogSearchController extends Controller
{
    /**
     * Display the human-centered search UI.
     * No node editors — just search, view, and map.
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
     * AJAX: Search nodes for select2 / autocomplete.
     */
    public function searchAjax(Request $request, CatalogSearchService $searcher)
    {
        $query = $request->input('q', '');
        $scheme = $request->input('scheme', 'UNSPSC');

        $results = $searcher->search($query, $scheme);

        return response()->json([
            'results' => $results->map(function ($node) {
                return [
                    'id'         => $node->id,
                    'code'       => $node->code,
                    'text'       => "[{$node->code}] {$node->title_en}",
                    'level'      => $node->level,
                    'scheme'     => $node->scheme,
                    'version'    => $node->version,
                    'has_mapping' => (bool) $node->snipeMapping,
                ];
            }),
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
     * Show the mapping editor for a specific node.
     */
    public function showMapping(Request $request)
    {
        $code = $request->input('code');
        $scheme = $request->input('scheme', 'UNSPSC');

        $searcher = app(CatalogSearchService::class);
        $node = $searcher->findByCode($scheme, $code);

        if (!$node) {
            return response()->json(['success' => false, 'message' => 'Node not found.'], 404);
        }

        return view('gov-classification::search.mapping', [
            'node' => $node,
            'currentMapping' => $node->snipeMapping,
        ]);
    }
}
