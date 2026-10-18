<?php

namespace GovStore\Tracking\Services;

use Illuminate\Support\Facades\DB;

final class TrackingTileMap
{
    public function tiles(array $offices, int $initiativeId, ?int $codeId = null): array
    {
        $areas = DB::table('gov_geo_areas')->whereIn('GeoAreaId', collect($offices)->map(fn ($o) => $o['profile']->geo_area_id ?? null)->filter())->get()->keyBy('GeoAreaId');
        $positions = json_decode(file_get_contents(__DIR__.'/../config/tile-maps/naogaon.json'), true, 512, JSON_THROW_ON_ERROR);
        $tiles = [];
        $fallback = 0;
        foreach ($offices as $o) {
            $geo = $areas->get($o['profile']->geo_area_id ?? null);
            $position = $positions['positions'][$geo->geo_code ?? ''] ?? null;
            // Place non-map offices below the schematic; no invented geographic position.
            if (! $position) {
                $position = ['x' => (++$fallback - 1) % 6 + 1, 'y' => 6 + intdiv($fallback - 1, 6)];
            }
            $tiles[] = ['name' => $geo->en_name ?? $o['name'], 'bn' => $geo->bn_name ?? ($o['name_bn'] ?? ''),
                'share' => $o['share'], 'alert' => $o['over'] || ($o['allocated'] > 0 && $o['done'] == 0),
                'x' => $position['x'], 'y' => $position['y'], 'href' => $codeId
                    ? route('gov.tracking.initiatives.tracking-codes.offices.show', [$initiativeId, $codeId, $o['id']])
                    : route('gov.tracking.initiatives.offices.show', [$initiativeId, $o['id']])];
        }

        return $tiles;
    }
}
