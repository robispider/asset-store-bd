<?php

namespace GovStore\GeoAreas\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\GeoAreas\Services\GeoAreaService;

class GeoAreaController extends Controller
{
    /** Shared geographical typeahead. */
    public function search(Request $request, GeoAreaService $geoService)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'restrict_hid' => ['nullable', 'string', 'max:255'],
            'types' => ['sometimes', 'array', 'max:20'],
            'types.*' => ['string', 'max:30'],
        ]);

        $results = $geoService->search(
            trim($validated['q'] ?? ''),
            $validated['types'] ?? [],
            $validated['restrict_hid'] ?? null
        );
        $useBangla = str_starts_with(strtolower(app()->getLocale()), 'bn');

        return response()->json($results->map(static function ($area) use ($useBangla) {
            return [
                'id' => $area->GeoAreaId,
                'text' => $useBangla ? $area->bn_name : $area->en_name,
                'en_name' => $area->en_name,
                'bn_name' => $area->bn_name,
                'geo_type' => GeoAreaService::canonicalType($area->geo_type),
                'geo_type_label' => __('geo_areas::types.' . GeoAreaService::canonicalType($area->geo_type)),
            ];
        })->values());
    }
}
