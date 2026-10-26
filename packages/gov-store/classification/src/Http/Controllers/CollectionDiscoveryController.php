<?php

namespace GovStore\Classification\Http\Controllers;

use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Services\CollectionMembershipService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CollectionDiscoveryController extends Controller
{
    public function index(TenantContext $context)
    {
        $collections = CatalogCollection::where('is_active', true)
            ->withCount('nodes')
            ->get()
            ->sortBy([['nodes_count', 'desc'], ['name', 'asc']])
            ->values();

        $featured = $collections->first(fn ($collection) => $collection->nodes_count > 0);
        $others = $collections->reject(fn ($collection) => $featured && $collection->is($featured))->values();
        $sections = collect();
        $adoptedCount = 0;

        if ($featured) {
            $sections = DB::table('gov_catalog_collection_nodes')
                ->where('collection_id', $featured->id)
                ->whereNotNull('section')
                ->selectRaw('section, count(*) as total')
                ->groupBy('section')
                ->orderByDesc('total')
                ->orderBy('section')
                ->pluck('total', 'section');

            $adoptedCount = DB::table('gov_catalog_collection_nodes as member')
                ->join('gov_catalog_snipe_mappings as mapping', 'mapping.code', '=', 'member.code')
                ->join('categories as category', 'category.id', '=', 'mapping.category_id')
                ->whereNull('category.deleted_at')
                ->where('member.collection_id', $featured->id)
                ->whereIn('mapping.category_id', $this->adoptedCategoryIds($context))
                ->distinct()
                ->count('member.code');
        }

        $masterCount = CatalogNode::count();

        return view('gov-classification::discover.collections.index', compact('collections', 'featured', 'others', 'sections', 'adoptedCount', 'masterCount'));
    }

    public function show($id, TenantContext $context)
    {
        $collection = CatalogCollection::where('is_active', true)->with(['nodes.catalogNode.snipeMapping.category', 'nodes.catalogNode.enrichment'])->findOrFail($id);

        $adoptedCategoryIds = $this->adoptedCategoryIds($context);
        $mappedTypes = DB::table('gov_catalog_snipe_mappings as mapping')->join('categories as category', 'category.id', '=', 'mapping.category_id')
            ->whereIn('mapping.code', $collection->nodes->pluck('code'))->whereNull('category.deleted_at')
            ->pluck('category.category_type', 'mapping.code');

        $adoptedCount = 0;
        $unadoptedCodes = [];

        foreach ($collection->nodes as $pivot) {
            $node = $pivot->catalogNode;
            $pivot->is_adopted = false;
            $pivot->inventory_type = $mappedTypes[$pivot->code] ?? $pivot->category_type;

            if ($node && $node->snipeMapping && $node->snipeMapping->category) {
                if (in_array($node->snipeMapping->category_id, $adoptedCategoryIds)) {
                    $pivot->is_adopted = true;
                    $adoptedCount++;
                }
            }

            if (! $pivot->is_adopted) {
                $unadoptedCodes[] = $pivot->code;
            }
        }

        $progress = $collection->nodes->count() > 0
            ? round(($adoptedCount / $collection->nodes->count()) * 100)
            : 0;

        return view('gov-classification::discover.collections.show', compact('collection', 'adoptedCount', 'progress', 'unadoptedCodes'));
    }

    /**
     * Category ids available to the working office or company, including global standards.
     */
    private function adoptedCategoryIds(TenantContext $context): array
    {
        $adopted = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where(function ($query) use ($context) {
                if ($context->companyId > 0) {
                    $query->where(fn ($scope) => $scope->where('scope_type', 'company')->where('scope_id', $context->companyId));
                }
                if (! $context->isCompanyAdmin && $context->locationId > 0) {
                    $query->orWhere(fn ($scope) => $scope->where('scope_type', 'location')->where('scope_id', $context->locationId));
                }
                if (! $context->companyId && (! $context->locationId || $context->isCompanyAdmin)) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();
        $global = DB::table('gov_category_governance')->where('governance_type', 'global')->pluck('category_id')->all();

        return array_merge($adopted, $global);
    }

    /**
     * API: List active collections for dropdown components.
     */
    public function listActive()
    {
        $collections = CatalogCollection::where('is_active', true)
            ->withCount('nodes')
            ->orderBy('name')
            ->get()
            ->map(fn ($collection) => [
                'id' => $collection->id,
                'name' => $collection->display_name,
                'icon' => $collection->icon,
                'count' => $collection->nodes_count,
            ]);

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    /**
     * API: Bulk attach nodes (or folders) to a collection. (Gated to Admins)
     */
    public function addNodes(Request $request, CollectionMembershipService $membershipService)
    {
        $user = auth()->user();

        // Strictly block non-admin users from modifying collection memberships
        if (! $user || (! $user->isSuperUser() && ! $user->hasAccess('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only system administrators can modify standard collections.',
            ], 403);
        }

        $request->validate([
            'collection_id' => 'required|integer|exists:gov_catalog_collections,id',
            'codes' => 'required|array|min:1',
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
                'message' => app(ActionFailure::class)->message($e),
            ], 500);
        }
    }
}
