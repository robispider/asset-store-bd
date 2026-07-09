<?php

namespace GovStore\GeoAreas\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\GeoAreas\Services\GeoAreaService;

class GeoAreaController extends Controller
{
    /**
     * Shared Geographical Search API. Fully decoupled from organizational models.
     */
    public function search(Request $request, GeoAreaService $geoService)
    {
        $term = $request->input('q', '');
        $restrictToHid = $request->input('restrict_hid', null);
        $types = $request->input('types', []);

        // Diagnostic visual pre-check
        if (empty($term) && !$request->ajax()) {
            $count = \GovStore\GeoAreas\Models\GeoArea::count();
            dd([
                'STATUS' => 'Shared Geographical Reference API is active!',
                'Total Registered Territories' => $count
            ]);
        }

        // Query our decoupled library service
        $results = $geoService->search($term, $types, $restrictToHid);

        $formatted = [];
        foreach ($results as $area) {
            $formatted[] = [
                'id' => $area->GeoAreaId,
                'text' => "{$area->en_name} ({$area->bn_name}) - " . ucwords(str_replace('_', ' ', $area->geo_type))
            ];
        }

        return response()->json($formatted);
    }
}