<?php

namespace GovStore\GeoView\Data;

use GovStore\GeoAreas\Services\GeoTree;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a viewer may see (plan §6.2): a set of offices and a set of area subtrees. Null means "no limit".
 * The geo view applies it as the second lock: facts from other offices are dropped, and child areas
 * outside the subtrees become no_access with no value or count.
 */
final class GeoScope
{
    /**
     * @param  int[]|null  $offices  location ids, null = every office
     * @param  int[]|null  $areas  GeoAreaIds whose subtrees are in scope, null = every area
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?array $offices,
        public readonly ?array $areas,
    ) {}

    /** Global: super users and public reference layers. */
    public static function all(): self
    {
        return new self('all', null, null);
    }

    /** These offices; every area (e.g. the tracking programme key: all offices of the programme). */
    public static function offices(array $ids): self
    {
        return new self('offices', array_values(array_unique(array_map('intval', $ids))), null);
    }

    /** Explicit offices and areas, for sources with their own rules. */
    public static function of(?array $offices, ?array $areas, string $kind = 'custom'): self
    {
        return new self($kind, $offices === null ? null : array_values(array_unique(array_map('intval', $offices))),
            $areas === null ? null : array_values(array_unique(array_map('intval', $areas))));
    }

    /**
     * The viewer's tenant scope: allowed (inventory) offices plus their ICT jurisdiction subtrees, or, without a
     * jurisdiction, the areas of the allowed offices. Global contexts see everything.
     */
    public static function tenant(TenantContext $context, bool $inventory = false, ?int $userId = null): self
    {
        if ($context->isGlobal) {
            return self::all();
        }
        $offices = $inventory ? $context->allowedInventoryLocationIds : $context->allowedLocationIds;
        if ($offices === null) {
            $offices = $context->locationId ? [$context->locationId] : [];
        }
        $areas = [];
        if ($userId && Schema::hasTable('gov_ict_jurisdictions') && Schema::hasColumn('gov_ict_jurisdictions', 'geo_area_id')) {
            $areas = DB::table('gov_ict_jurisdictions')->where('user_id', $userId)->pluck('geo_area_id')->map(fn ($id) => (int) $id)->all();
        }
        if (! $areas && $offices && Schema::hasTable('gov_location_profiles') && Schema::hasColumn('gov_location_profiles', 'geo_area_id')) {
            $areas = DB::table('gov_location_profiles')->whereIn('location_id', $offices)->whereNotNull('geo_area_id')
                ->pluck('geo_area_id')->map(fn ($id) => (int) $id)->all();
        }

        return new self('tenant', array_map('intval', $offices), array_values(array_unique($areas)));
    }

    public function allowsOffice(int $locationId): bool
    {
        return $this->offices === null || in_array($locationId, $this->offices, true);
    }

    /**
     * 'in' when the area lies inside a scope subtree, 'partial' when it only contains one (a division around the
     * viewer's district), 'out' otherwise.
     */
    public function areaState(int $areaId, GeoTree $tree): string
    {
        if ($this->areas === null) {
            return 'in';
        }
        $chain = $tree->chain($areaId);
        foreach ($this->areas as $root) {
            if (in_array($root, $chain, true)) {
                return 'in';
            }
        }
        foreach ($this->areas as $root) {
            if ($tree->isUnder($root, $areaId)) {
                return 'partial';
            }
        }

        return 'out';
    }

    public function hash(): string
    {
        return sha1(json_encode([$this->kind, $this->offices, $this->areas]));
    }
}
