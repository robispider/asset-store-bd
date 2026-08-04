<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\TenantScope\Contexts\TenantContext;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class OfficeCopyController extends Controller
{
    public function index(TenantContext $context)
    {
        // Fetch offices within the same Ministry (Company) that actually have adopted categories
        $sourceOffices = Location::withoutGlobalScopes()
            ->select('locations.id', 'locations.name')
            ->where('locations.company_id', $context->companyId)
            ->where('locations.id', '!=', $context->locationId) // Exclude self
            ->whereIn('locations.id', function ($query) {
                $query->select('scope_id')
                      ->from('gov_tenant_scope_mappings')
                      ->where('scope_type', 'location')
                      ->where('reference_type', 'category');
            })
            ->orderBy('locations.name')
            ->get();

        return view('gov-classification::adopt.copy', compact('sourceOffices'));
    }

    /**
     * AJAX endpoint: Fetches all category codes adopted by the selected source office.
     * These codes are then passed to the frontend Bulk Adoption Modal.
     */
    public function fetchSourceCodes(Request $request)
    {
        $request->validate(['source_location_id' => 'required|integer']);

        $codes = DB::table('gov_tenant_scope_mappings as map')
            ->join('gov_catalog_snipe_mappings as snipe', 'map.reference_id', '=', 'snipe.category_id')
            ->where('map.scope_type', 'location')
            ->where('map.scope_id', $request->source_location_id)
            ->where('map.reference_type', 'category')
            ->where('map.is_active', true) // Only grab active (un-archived) categories
            ->pluck('snipe.code')
            ->toArray();

        return response()->json(['success' => true, 'codes' => $codes]);
    }
}