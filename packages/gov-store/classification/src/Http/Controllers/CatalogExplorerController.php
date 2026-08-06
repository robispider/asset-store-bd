<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Models\CatalogNode;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class CatalogExplorerController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $parentCode = $request->input('parent');
        $mode = $request->input('mode', 'master'); // 'master' or 'local'
        
        // 1. Resolve Active Scope for Adoption Checks
        $scopeType = $context->companyId > 0 ? 'company' : 'location';
        $scopeId = $context->companyId > 0 ? $context->companyId : $context->locationId;
        
        // Fetch all native Snipe-IT category IDs adopted by this scope
        $adoptedCategoryIds = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();

        // Include Tier-1 Global Standards implicitly
        $globalCategoryIds = DB::table('gov_category_governance')
            ->where('governance_type', 'global')
            ->pluck('category_id')
            ->toArray();

        $activeCategoryIds = array_unique(array_merge($adoptedCategoryIds, $globalCategoryIds));

        // 2. Fetch Breadcrumbs (Ancestors)
        $breadcrumbs = collect();
        if ($parentCode) {
            $currentNode = CatalogNode::where('code', $parentCode)->firstOrFail();
            $codes = array_filter(explode('/', $currentNode->hid));
            $breadcrumbs = CatalogNode::whereIn('code', $codes)->orderBy('level', 'asc')->get();
        }

        // 3. Build the Base Query for Children Nodes
        $query = CatalogNode::with('snipeMapping')->orderBy('code', 'asc');
        
        if ($parentCode) {
            $query->where('parent_code', $parentCode);
        } else {
            $query->where('level', 1); // Segments (Root level)
        }

        // 4. APPLY THE LOCAL PRUNING FILTER (The Magic)
        if ($mode === 'local') {
            $query->where(function ($q) use ($activeCategoryIds) {
                // If it's a Commodity (Level 4), it MUST be directly mapped to an active category
                $q->where(function ($sub) use ($activeCategoryIds) {
                    $sub->where('level', 4)
                        ->whereHas('snipeMapping', function ($mapQ) use ($activeCategoryIds) {
                            $mapQ->whereIn('category_id', $activeCategoryIds);
                        });
                });
                
                // If it's a Folder (Level < 4), it must have at least one active descendant commodity
                $q->orWhere(function ($sub) use ($activeCategoryIds) {
                    $sub->where('level', '<', 4)
                        ->whereExists(function ($existsQ) use ($activeCategoryIds) {
                            $existsQ->select(DB::raw(1))
                                ->from('gov_catalog_nodes as descendants')
                                ->join('gov_catalog_snipe_mappings as map', 'descendants.code', '=', 'map.code')
                                ->whereColumn('descendants.hid', 'LIKE', DB::raw("CONCAT(gov_catalog_nodes.hid, '%')"))
                                ->where('descendants.level', 4)
                                ->whereIn('map.category_id', $activeCategoryIds);
                        });
                });
            });
        }
        
        $nodes = $query->paginate(100);

        // 5. Map Adoption Status for the UI
        $nodes->getCollection()->transform(function ($node) use ($adoptedCategoryIds, $globalCategoryIds) {
            $node->is_folder = ($node->level < 4);
            $node->is_adopted = false;
            $node->is_global = false;
            
            if (!$node->is_folder && $node->snipeMapping) {
                $catId = $node->snipeMapping->category_id;
                $node->is_adopted = in_array($catId, $adoptedCategoryIds);
                $node->is_global = in_array($catId, $globalCategoryIds);
            }
            return $node;
        });

        // Determine if current user can modify collections
        $canManageCollections = auth()->user() && (auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin'));

        return view('gov-classification::discover.explorer.index', compact(
            'nodes', 
            'breadcrumbs', 
            'parentCode', 
            'mode', 
            'canManageCollections'
        ));
    }
}