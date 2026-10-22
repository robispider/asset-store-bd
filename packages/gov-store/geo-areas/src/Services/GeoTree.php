<?php

namespace GovStore\GeoAreas\Services;

use GovStore\GeoAreas\Models\GeoArea;
use Illuminate\Support\Facades\Cache;

/**
 * In-memory index of the administrative tree (country → ward, no mauza/village rows).
 *
 * Parents are resolved through parent_geo_code, not hid: city corporations and paurashavas have no hid,
 * but every row has a parent code. Built once per dataset signature and cached.
 */
class GeoTree
{
    /** Types drawn as urban units: coloured on the map, not drillable, rolled up to their parent area. */
    public const URBAN_TYPES = ['city', 'pourasabha', 'city_thana', 'pouro_ward', 'thana_ward'];

    /** @var array<int, array{id:int, type:string, level:int, parent:?int, en:string, bn:string, hid:?string}>|null */
    private ?array $nodes = null;

    /** @var array<int, int[]>|null */
    private ?array $children = null;

    /** @return array<int, array{id:int, type:string, level:int, parent:?int, en:string, bn:string, hid:?string}> */
    public function nodes(): array
    {
        if ($this->nodes === null) {
            $this->nodes = $this->load();
            $this->children = [];
            foreach ($this->nodes as $id => $node) {
                if ($node['parent'] !== null) {
                    $this->children[$node['parent']][] = $id;
                }
            }
        }

        return $this->nodes;
    }

    public function node(int $id): ?array
    {
        return $this->nodes()[$id] ?? null;
    }

    public function root(): ?int
    {
        foreach ($this->nodes() as $id => $node) {
            if ($node['type'] === 'country') {
                return $id;
            }
        }

        return null;
    }

    /** @return int[] child ids, sorted by English name */
    public function childIds(int $id): array
    {
        $this->nodes();
        $ids = $this->children[$id] ?? [];
        usort($ids, fn ($a, $b) => strcmp($this->nodes[$a]['en'], $this->nodes[$b]['en']) ?: $a <=> $b);

        return $ids;
    }

    /** @return int[] root first, $id last; empty for an unknown id */
    public function chain(int $id): array
    {
        $out = [];
        $guard = 0;
        for ($current = $id; $current !== null && isset($this->nodes()[$current]) && $guard++ < 10; $current = $this->nodes[$current]['parent']) {
            array_unshift($out, $current);
        }

        return $out;
    }

    public function isUnder(int $id, int $ancestor): bool
    {
        return in_array($ancestor, $this->chain($id), true);
    }

    /** Nearest ancestor-or-self that is not an urban unit (city corporation → district, paurashava → upazila). */
    public function rollup(int $id): ?int
    {
        foreach (array_reverse($this->chain($id)) as $candidate) {
            if (! in_array($this->nodes[$candidate]['type'], self::URBAN_TYPES, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Lowest common ancestor of several areas; the root when the list is empty. */
    public function commonAncestor(array $ids): ?int
    {
        $chains = array_values(array_filter(array_map(fn ($id) => $this->chain((int) $id), $ids)));
        if (! $chains) {
            return $this->root();
        }
        $out = $chains[0][0];
        foreach ($chains[0] as $i => $candidate) {
            foreach ($chains as $chain) {
                if (($chain[$i] ?? null) !== $candidate) {
                    return $out;
                }
            }
            $out = $candidate;
        }

        return $out;
    }

    public function isUrban(int $id): bool
    {
        return in_array($this->node($id)['type'] ?? null, self::URBAN_TYPES, true);
    }

    /** Name in the given locale; $short drops the type suffix ("Patuakhali District" → "Patuakhali"). */
    public function name(int $id, ?string $locale = null, bool $short = false): string
    {
        $node = $this->node($id);
        if (! $node) {
            return (string) $id;
        }
        $bangla = str_starts_with(strtolower($locale ?? app()->getLocale()), 'bn');
        $name = $bangla && $node['bn'] !== '' ? $node['bn'] : $node['en'];
        if (! $bangla && $name === strtoupper($name)) {
            $name = ucwords(strtolower($name));
        }
        if ($short) {
            $name = preg_replace(['/ (District|Division|Upazilla|Upazila|Zila)$/i', '/ (জেলা|বিভাগ|উপজেলা)$/u'], '', $name);
        }

        return $name;
    }

    public function forget(): void
    {
        $this->nodes = $this->children = null;
        Cache::forget($this->cacheKey());
    }

    private function load(): array
    {
        return Cache::remember($this->cacheKey(), 86400, function () {
            $rows = GeoArea::query()
                ->whereNotIn('geo_type', GeoAreaService::LOCALITY_TYPES)
                ->get(['GeoAreaId', 'hid', 'geo_type', 'geo_code', 'parent_geo_code', 'bn_name', 'en_name', 'GeoLevel']);
            $byCode = [];
            foreach ($rows as $row) {
                $byCode[(string) $row->geo_code] = (int) $row->GeoAreaId;
            }
            $nodes = [];
            foreach ($rows as $row) {
                $id = (int) $row->GeoAreaId;
                $type = GeoAreaService::canonicalType((string) $row->geo_type);
                $parent = $type === 'country' ? null : ($byCode[(string) $row->parent_geo_code] ?? null);
                $nodes[$id] = [
                    'id' => $id,
                    'type' => $type,
                    'level' => is_numeric($row->GeoLevel) ? (int) $row->GeoLevel : 0,
                    'parent' => $parent === $id ? null : $parent,
                    'en' => trim((string) $row->en_name),
                    'bn' => trim((string) $row->bn_name),
                    'hid' => $row->hid ?: null,
                ];
            }

            return $nodes;
        });
    }

    private function cacheKey(): string
    {
        $stats = GeoArea::query()->whereNotIn('geo_type', GeoAreaService::LOCALITY_TYPES)
            ->selectRaw('count(*) as n, max(GeoAreaId) as top')->first();

        return 'gov-geo-tree:'.($stats->n ?? 0).':'.($stats->top ?? 0);
    }
}
