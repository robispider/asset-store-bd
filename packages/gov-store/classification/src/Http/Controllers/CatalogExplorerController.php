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
        
        // Resolve Scope for Adoption Checkmarks
        $scopeType = $context->companyId > 0 ? 'company' : 'location';
        $scopeId = $context->companyId > 0 ? $context->companyId : $context->locationId;
        
        $adoptedCategoryIds = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();

        // 1. Fetch Breadcrumbs (Ancestors)
        $breadcrumbs = collect();
        if ($parentCode) {
            $currentNode = CatalogNode::where('code', $parentCode)->firstOrFail();
            $codes = array_filter(explode('/', $currentNode->hid));
            $breadcrumbs = CatalogNode::whereIn('code', $codes)->orderBy('level', 'asc')->get();
        }

        // 2. Fetch Children of Current Node (or Roots if no parent)
        $query = CatalogNode::with('snipeMapping')->orderBy('code', 'asc');
        if ($parentCode) {
            $query->where('parent_code', $parentCode);
        } else {
            $query->where('level', 1); // Segments
        }
        
        $nodes = $query->paginate(100);

        // 3. Map Adoption Status
        $nodes->getCollection()->transform(function ($node) use ($adoptedCategoryIds) {
            $node->is_folder = ($node->level < 4); // Segment, Family, Class = Folders
            $node->is_adopted = false;
            
            if (!$node->is_folder && $node->snipeMapping) {
                $node->is_adopted = in_array($node->snipeMapping->category_id, $adoptedCategoryIds);
            }
            return $node;
        });

        return view('gov-classification::discover.explorer.index', compact('nodes', 'breadcrumbs', 'parentCode'));
    }
}