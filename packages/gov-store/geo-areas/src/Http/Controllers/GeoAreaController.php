<?php

namespace GovStore\GeoAreas\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\GeoAreas\Services\GeoTree;

class GeoAreaController extends Controller
{
    /** Names, types and drill hints of an area's children (reference data; no business data). */
    public function children(int $area, GeoAreaService $geoService, GeoTree $tree)
    {
        abort_unless($tree->node($area), 404);
        $locale = app()->getLocale();

        return response()->json([
            'id' => $area,
            'name' => $tree->name($area, $locale),
            'trail' => array_map(fn ($id) => ['id' => $id, 'name' => $tree->name($id, $locale, true)], $tree->chain($area)),
            'children' => $geoService->children($area, $locale),
        ]);
    }

    /** Shared geographical typeahead. */
    public function search(Request $request, GeoAreaService $geoService)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'restrict_hid' => ['nullable', 'string', 'max:255'],
            'types' => ['sometimes', 'array', 'max:20'],
            'types.*' => ['string', 'max:30'],
            'with_parent' => ['nullable', 'boolean'],
        ]);

        $results = $geoService->search(
            trim($validated['q'] ?? ''),
            $validated['types'] ?? [],
            $validated['restrict_hid'] ?? null
        );
        $useBangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
        $tree = ! empty($validated['with_parent']) ? app(GeoTree::class) : null;

        return response()->json($results->map(static function ($area) use ($useBangla, $tree) {
            // The geo view's find box opens the parent and selects the area (geo-view plan §4.1).
            $extra = $tree ? [
                'parent_id' => $tree->node((int) $area->GeoAreaId)['parent'] ?? null,
                'level' => $tree->node((int) $area->GeoAreaId)['level'] ?? null,
                'trail' => array_map(fn ($id) => $tree->name($id, null, true), array_slice($tree->chain((int) $area->GeoAreaId), 1, -1)),
            ] : [];

            return $extra + [
                'id' => $area->GeoAreaId,
                'text' => $useBangla ? $area->bn_name : $area->en_name,
                'en_name' => $area->en_name,
                'bn_name' => $area->bn_name,
                'geo_type' => GeoAreaService::canonicalType($area->geo_type),
                'geo_type_label' => __('geo_areas::types.' . GeoAreaService::canonicalType($area->geo_type)),
                'bbs_code' => $area->bbs_code,
                'code_status' => $area->code_status,
            ];
        })->values());
    }
}
