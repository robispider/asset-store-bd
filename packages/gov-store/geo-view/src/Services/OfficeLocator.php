<?php

namespace GovStore\GeoView\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Office (location id) → its GeoAreaId and whether that placement is verified (gov_location_profiles). */
class OfficeLocator
{
    /** @var array<int, array{area:?int, verified:bool}> */
    private array $memo = [];

    /**
     * @param  int[]  $locationIds
     * @return array<int, array{area:?int, verified:bool}>
     */
    public function locate(array $locationIds): array
    {
        $missing = array_values(array_diff(array_unique(array_map('intval', $locationIds)), array_keys($this->memo)));
        if ($missing && Schema::hasTable('gov_location_profiles') && Schema::hasColumn('gov_location_profiles', 'geo_area_id')) {
            $verified = Schema::hasColumn('gov_location_profiles', 'geo_area_verified_at');
            foreach (array_chunk($missing, 500) as $chunk) {
                $rows = DB::table('gov_location_profiles')->whereIn('location_id', $chunk)
                    ->get(array_merge(['location_id', 'geo_area_id'], $verified ? ['geo_area_verified_at'] : []));
                foreach ($rows as $row) {
                    $this->memo[(int) $row->location_id] = [
                        'area' => $row->geo_area_id ? (int) $row->geo_area_id : null,
                        'verified' => $verified && $row->geo_area_verified_at !== null,
                    ];
                }
            }
        }
        $out = [];
        foreach ($locationIds as $id) {
            $out[(int) $id] = $this->memo[(int) $id] ?? ['area' => null, 'verified' => false];
        }

        return $out;
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
