<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\Classification\Models\CatalogCollection;
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

        // Determine active scope for adoption checks
        $scopeType = $context->companyId > 0 ? 'company' : 'location';
        $scopeId = $context->companyId > 0 ? $context->companyId : $context->locationId;

        // Fetch all categories currently adopted by this scope
        $adoptedCategoryIds = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();

        $adoptedCount = 0;
        $unadoptedCodes = [];

        // Map status to each node in the collection
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
}