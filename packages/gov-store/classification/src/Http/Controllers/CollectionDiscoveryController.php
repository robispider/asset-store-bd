<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Models\CatalogCollectionNode;
use GovStore\Classification\Services\CollectionMembershipService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class CollectionDiscoveryController extends Controller
{
    public function index()
    {
        $collections = CatalogCollection::where('is_active', true)
            ->withCount('nodes')
            ->orderBy('name')
            ->get();

        return view('gov-classification::discover.collections.index', compact('collections'));
    }

    public function show($id, TenantContext $context)
    {
        $collection = CatalogCollection::with(['nodes.catalogNode.snipeMapping'])->findOrFail($id);

        $scopeType = $context->companyId > 0 ? 'company' : 'location';
        $scopeId = $context->companyId > 0 ? $context->companyId : $context->locationId;

        $adoptedCategoryIds = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();

        $adoptedCount = 0;
        $unadoptedCodes = [];

        foreach ($collection->nodes as $pivot) {
            $node = $pivot->catalogNode;
            $pivot->is_adopted = false;

            if ($node && $node->snipeMapping) {
                if (in_array($node->snipeMapping->category_id, $adoptedCategoryIds)) {
                    $pivot->is_adopted = true;
                    $adoptedCount++;
                }
            }

            if (!$pivot->is_adopted) {
                $unadoptedCodes[] = $pivot->code;
            }
        }

        $progress = $collection->nodes->count() > 0 
            ? round(($adoptedCount / $collection->nodes->count()) * 100) 
            : 0;

        return view('gov-classification::discover.collections.show', compact('collection', 'adoptedCount', 'progress', 'unadoptedCodes'));
    }

    /**
     * API: List active collections for dropdown components.
     */
    public function listActive()
    {
        $collections = CatalogCollection::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        return response()->json([
            'success' => true,
            'collections' => $collections
        ]);
    }

    /**
     * API: Bulk attach nodes (or folders) to a collection. (Gated to Admins)
     */
    public function addNodes(Request $request, CollectionMembershipService $membershipService)
    {
        $user = auth()->user();
        
        // Strictly block non-admin users from modifying collection memberships
        if (!$user || (!$user->isSuperUser() && !$user->hasAccess('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only system administrators can modify standard collections.'
            ], 403);
        }

        $request->validate([
            'collection_id' => 'required|integer|exists:gov_catalog_collections,id',
            'codes'         => 'required|array|min:1'
        ]);

        try {
            $result = $membershipService->addNodesToCollection(
                $request->collection_id,
                $request->codes
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add items: ' . $e->getMessage()
            ], 500);
        }
    }
}