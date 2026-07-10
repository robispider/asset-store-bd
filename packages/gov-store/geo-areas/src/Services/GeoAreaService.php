<?php

namespace GovStore\GeoAreas\Services;

use GovStore\GeoAreas\Models\GeoArea;
use Illuminate\Support\Collection;

class GeoAreaService
{
    /**
     * Resolves a single geographical territory by its master ID.
     */
    public function getById(int $id): ?GeoArea
    {
        return GeoArea::find($id);
    }
  /**
     * Returns all registered District-level territories in Bangladesh.
     * Decoupled API: consumed by other packages to populate filter select lists.
     */
    public function getAllDistricts(): Collection
    {
        return GeoArea::where('geo_type', 'district')->orderBy('en_name')->get();
    }

    /**
     * Parses the hierarchical 'hid' path and extracts the English names of the 
     * corresponding Upazila (city) and Zila/District (state) nodes.
     */
    public function resolveParentNames(string $hid): array
    {
        $parts = array_filter(explode('/', $hid));
        $city = '';
        $state = '';

        foreach ($parts as $code) {
            $parent = GeoArea::where('geo_code', $code)->first();
            if ($parent) {
                if (in_array($parent->geo_type, ['upazilla', 'city'])) {
                    $city = $parent->en_name;
                }
                if ($parent->geo_type === 'district') {
                    $state = $parent->en_name;
                }
            }
        }

        return [
            'city' => $city,
            'state' => $state
        ];
    }
    /**
     * Generic Search API: Filters by term, supports matching arrays of geo-types,
     * and restricts results to an active hierarchy tree branch using high-performance 'hid' indexing.
     */
    public function search(string $term, array $types = [], ?string $restrictToHid = null, int $limit = 15): Collection
    {
        $query = GeoArea::query();

        // 1. Live text match on both English and Bengali fields
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('en_name', 'like', "%{$term}%")
                  ->orWhere('bn_name', 'like', "%{$term}%");
            });
        }

        // 2. Multi-type filter (supports upazila, union, pourasabha, ward, etc.)
        if (!empty($types)) {
            $query->whereIn('geo_type', $types);
        }

        // 3. Structural Boundary Scoping (O(1) tree containment)
        if (!empty($restrictToHid)) {
            $query->where('hid', 'like', $restrictToHid . '%');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Checks if a target location lies within an administrator's boundary.
     */
    public function isWithinBoundary(int $officerGeoId, int $targetOfficeGeoId): bool
    {
        $officerArea = $this->getById($officerGeoId);
        $targetArea = $this->getById($targetOfficeGeoId);

        if (!$officerArea || !$targetArea) {
            return false;
        }

        return str_starts_with($targetArea->hid, $officerArea->hid);
    }
}