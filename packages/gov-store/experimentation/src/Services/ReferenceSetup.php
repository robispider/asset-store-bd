<?php

namespace GovStore\Experimentation\Services;

use App\Models\Group;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReferenceSetup
{
    public function populate(): void
    {
        // Add missing bundled master rows only. Existing master data is never overwritten.
        $handle = fopen(base_path('packages/gov-store/geo-areas/src/database/data/geo_areas.csv'), 'r');
        $batch = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row[0] = preg_replace('/^\x{FEFF}/u', '', $row[0] ?? '');
            if (count($row) < 9 || ! is_numeric($row[0])) {
                continue;
            }
            $batch[] = ['GeoAreaId' => (int) $row[0], 'hid' => $row[1] && $row[1] !== '?' ? $row[1] : null,
                'geo_type' => trim($row[2]), 'parent_geo_code' => $row[3] ? (int) $row[3] : null, 'geo_code' => (int) $row[4],
                'bn_name' => $row[5], 'domain' => $row[6] && $row[6] !== '?' ? $row[6] : null, 'en_name' => $row[7], 'GeoLevel' => (int) $row[8], 'created_at' => now(), 'updated_at' => now()];
            if (count($batch) === 250) {
                DB::table('gov_geo_areas')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch) {
            DB::table('gov_geo_areas')->insertOrIgnore($batch);
        }
        if (DB::table('gov_geo_areas')->where('GeoLevel', 1)->whereNotNull('hid')->where('hid', '!=', '')->count() !== 8) {
            throw new RuntimeException('The installed geography master conflicts with the bundled eight-division dataset.');
        }
        foreach (['ICT Operations' => 'ict_operations', 'Company Administration' => 'company_operations'] as $name => $profile) {
            if (Group::where('name', $name)->exists()) {
                continue;
            }
            $group = new Group;
            $group->forceFill(['name' => $name, 'permissions' => json_encode(array_fill_keys(config('govstore-permissions.profiles.'.$profile, []), '1')), 'notes' => 'GovStore role reference setup']);
            if (! $group->save()) {
                throw new RuntimeException('Could not create GovStore reference permission group.');
            }
        }
    }
}
