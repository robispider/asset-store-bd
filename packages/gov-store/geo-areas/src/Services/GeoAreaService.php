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

        // A null/empty officer hid must DENY (fail closed). Otherwise str_starts_with($x, '')
        // returns true for every territory — a full boundary bypass.
        if (empty($officerArea->hid) || empty($targetArea->hid)) {
            return false;
        }

        return str_starts_with($targetArea->hid, $officerArea->hid);
    }
}