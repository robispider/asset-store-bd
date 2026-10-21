<?php

namespace GovStore\GeoView\Services;

use GovStore\GeoAreas\Services\BoundarySet;
use GovStore\GeoAreas\Services\GeoTree;
use GovStore\GeoView\Contracts\AreaGrainSource;
use GovStore\GeoView\Contracts\CachesFacts;
use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Data\FilterDefinition;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;
use GovStore\GeoView\Data\OfficeFact;
use Illuminate\Support\Facades\Cache;

/**
 * Builds gs-geo-data/1 payloads (plan §6.4) and details rows for one source and request. The source has
 * already applied its domain rules (lock 1); this service masks again with the source's GeoScope (lock 2):
 * facts from other offices are dropped and child areas outside scope carry no value and no count.
 */
class GeoDataService
{
    public function __construct(
        private GeoTree $tree,
        private BoundarySet $boundaries,
        private GeoAggregator $aggregator,
        private OfficeLocator $locator,
    ) {}

    public function payload(GeoDataSource $source, GeoRequest $request): array
    {
        $scope = $source->scope($request);
        $layers = $source->layers($request);
        $office = $source->grain() === GeoDataSource::GRAIN_OFFICE;
        $offices = $office ? $this->offices($source, $request, $scope, $layers) : [];
        $area = $this->resolveArea($request->area, $office ? $offices : [], $scope);
        $applicable = $this->applicability($layers, $offices, $office);
        [$layer, $requested] = $this->chooseLayer($layers, $applicable, $request);
        $locale = $request->locale();

        $children = $this->children($area, $request->urban);
        $areaState = $scope->areaState($area, $this->tree);
        $entries = [];
        $buckets = $office ? $this->aggregator->bucket($offices, $area, $children, $request->urban) : null;
        $areaValues = ! $office && $source instanceof AreaGrainSource && $areaState !== 'out'
            ? $source->areaValues($request, $children, $layer->key) : [];
        foreach ($children as $child) {
            $state = $areaState === 'out' ? 'out' : $scope->areaState($child, $this->tree);
            $entry = [
                'state' => 'no_data',
                'name' => $this->tree->name($child, $locale),
                'short' => $this->tree->name($child, $locale, true),
                'type' => (string) __('geo_areas::types.'.$this->tree->node($child)['type'], [], $locale),
                'drillable' => $this->boundaries->hasChildrenFile($child) && ! $this->tree->isUrban($child),
                'shape' => $this->boundaries->hasShape($child),
            ];
            if ($state === 'out') {
                // No value, no count: the viewer must not learn anything about this area.
                $entries[(string) $child] = ['state' => 'no_access', 'drillable' => false] + $entry;

                continue;
            }
            if ($office) {
                $entry = $this->officeEntry($entry, $offices, $buckets['children'][$child] ?? [], $layer);
            } else {
                $value = $state === 'partial' ? null : ($areaValues[$child] ?? null);
                $entry['state'] = $value === null ? 'no_data' : 'ok';
                $entry['value'] = $this->number($value);
                if ($value !== null && ($display = $layer->display($value)) !== null) {
                    $entry['display'] = $display;
                }
            }
            if ($state === 'partial') {
                // Office grain counts only in-scope offices; area grain withholds the figure (it would include
                // areas outside scope).
                $entry['partial'] = true;
                $entry['state'] = 'partial';
            }
            $entries[(string) $child] = $entry;
        }

        $values = array_values(array_filter(array_map(fn ($e) => in_array($e['state'], ['ok', 'partial'], true) ? ($e['value'] ?? null) : null, $entries), fn ($v) => $v !== null));
        $total = $office && $areaState !== 'out' ? $this->aggregator->aggregate($offices, $buckets['inside'], $layer)['value']
            : ($source instanceof AreaGrainSource && $areaState === 'in' ? ($source->areaValues($request, [$area], $layer->key)[$area] ?? null) : null);
        $national = $office ? $this->aggregator->aggregate($offices, array_keys($offices), $layer)['value'] : null;
        $hereIds = $office && $areaState !== 'out' ? $buckets['here'] : [];
        $unplacedIds = $office && $areaState !== 'out' ? $buckets['unplaced'] : [];

        $layerOut = ['key' => $layer->key, 'label' => $layer->label, 'unit' => $layer->unit, 'format' => $layer->format, 'aggregation' => $layer->aggregation];
        if ($requested !== null && $requested !== $layer->key) {
            $from = $this->layerByKey($layers, $requested);
            $layerOut['fallback_from'] = $requested;
            $layerOut['fallback_reason'] = $from && $from->aggregation === 'ratio' ? 'no_denominator' : 'not_applicable';
            if ($from && $from->notApplicableNote) {
                $layerOut['fallback_note'] = $from->notApplicableNote;
            }
        }

        return [
            'contract' => 'gs-geo-data/1',
            'source' => $source->key(),
            'area' => [
                'id' => $area,
                'level' => $this->tree->node($area)['level'] ?? 0,
                'name' => $this->tree->name($area, $locale),
                'type' => $this->tree->node($area)['type'] ?? null,
                'parent' => $this->tree->node($area)['parent'] ?? null,
                'trail' => array_map(fn ($id) => ['id' => $id, 'name' => $this->tree->name($id, $locale), 'short' => $this->tree->name($id, $locale, true)], $this->tree->chain($area)),
            ],
            'level' => min(4, ($this->tree->node($area)['level'] ?? 0) + 1),
            'boundary' => ['version' => $this->boundaries->version(), 'children' => $this->boundaries->childrenUrl($area)],
            'layer' => $layerOut,
            'layers' => array_map(fn (LayerDefinition $l) => ['key' => $l->key, 'label' => $l->label, 'applicable' => $applicable[$l->key]], $layers),
            'stats' => [
                'min' => $values ? $this->number(min($values)) : null,
                'max' => $values ? $this->number(max($values)) : null,
                'national' => $this->number($national),
                'total' => $this->number($total),
            ],
            'areas' => $entries,
            'here' => ['offices' => count($hereIds), 'value' => $hereIds ? $this->number($this->aggregator->aggregate($offices, $hereIds, $layer)['value']) : null],
            'unplaced' => ['offices' => count($unplacedIds)],
            'coverage' => $office ? [
                'offices' => count($offices),
                'placed' => count(array_filter($offices, fn ($o) => $o['area'] !== null)),
                'verified' => count(array_filter($offices, fn ($o) => $o['verified'])),
                'rolled_up' => count(array_filter($offices, fn ($o) => $o['area'] !== null && $this->tree->isUrban($o['area']))),
            ] : null,
            'filters' => array_map(fn (FilterDefinition $f) => $f->toArray($request->filter($f->key)), $source->filters($request)),
            'scope' => ['kind' => $scope->kind === 'custom' ? 'tenant' : $scope->kind, 'partial' => $areaState === 'partial'
                || in_array('partial', array_column($entries, 'state'), true)],
            'attribution' => $this->boundaries->attribution(),
            'links' => ['details' => null],
        ];
    }

    /**
     * Side-panel rows for one area: the source's own rows, or its in-scope offices under the area.
     *
     * @return array{area:int, offices: array}
     */
    public function details(GeoDataSource $source, GeoRequest $request, int $areaId): array
    {
        $scope = $source->scope($request);
        if (! $this->tree->node($areaId) || $scope->areaState($areaId, $this->tree) === 'out') {
            return ['area' => $areaId, 'offices' => []];
        }
        $own = $source->details($request, $areaId);
        if ($own !== null) {
            return ['area' => $areaId, 'offices' => array_values($own)];
        }
        if ($source->grain() !== GeoDataSource::GRAIN_OFFICE) {
            return ['area' => $areaId, 'offices' => []];
        }
        $layers = $source->layers($request);
        $offices = $this->offices($source, $request, $scope, $layers);
        [$layer] = $this->chooseLayer($layers, $this->applicability($layers, $offices, true), $request);
        $locale = $request->locale();
        $rows = [];
        foreach ($offices as $id => $office) {
            if ($office['area'] === null || ! $this->tree->isUnder($office['area'], $areaId)) {
                continue;
            }
            $value = $this->aggregator->aggregate($offices, [$id], $layer)['value'];
            $row = [
                'id' => $id,
                'name' => $office['name'] ?? (string) $id,
                'area' => $office['area'],
                'area_name' => $this->tree->name($office['area'], $locale),
                'value' => $this->number($value),
                'link' => $office['link'],
                'here' => $office['area'] === $areaId,
            ];
            if ($value !== null && ($display = $layer->display($value)) !== null) {
                $row['display'] = $display;
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => [! $a['here'], $a['name']] <=> [! $b['here'], $b['name']]);

        return ['area' => $areaId, 'offices' => $rows];
    }

    /**
     * Facts → offices keyed by location id, after the scope mask; optionally cached per source (plan §6.6).
     *
     * @param  LayerDefinition[]  $layers
     * @return array<int, array{area:?int, verified:bool, values:array, name:?string, link:?string}>
     */
    private function offices(GeoDataSource $source, GeoRequest $request, GeoScope $scope, array $layers): array
    {
        $load = function () use ($source, $request, $layers) {
            $facts = [];
            foreach ($source->facts($request, array_map(fn ($l) => $l->key, $layers)) as $fact) {
                if ($fact instanceof OfficeFact) {
                    $facts[] = $fact;
                }
            }

            return $facts;
        };
        $ttl = $source instanceof CachesFacts ? $source->factsTtl() : 0;
        $facts = $ttl > 0
            ? Cache::remember('geo-view:facts:'.$source->key().':'.$request->factsKey().':'.$scope->hash(), $ttl, $load)
            : $load();
        $located = $this->locator->locate(array_map(fn (OfficeFact $f) => $f->locationId, $facts));
        $offices = [];
        foreach ($facts as $fact) {
            if (! $scope->allowsOffice($fact->locationId)) {
                continue;   // lock 2: the source returned an office outside its own scope
            }
            $area = $fact->areaId ?? $located[$fact->locationId]['area'];
            if ($area !== null && $scope->areas !== null && $scope->areaState($area, $this->tree) !== 'in') {
                continue;
            }
            $offices[$fact->locationId] = [
                'area' => $area,
                'verified' => $located[$fact->locationId]['verified'] ?? false,
                'values' => $fact->values,
                'name' => $fact->name,
                'link' => $fact->link,
            ];
        }

        return $offices;
    }

    /** 'auto' opens at the smallest drawable area containing every office (or the scope); clamps to drawable areas. */
    private function resolveArea(int|string $requested, array $offices, GeoScope $scope): int
    {
        $root = $this->boundaries->root() ?? $this->tree->root();
        if ($requested === 'auto' || $requested === '' || $requested === null) {
            $placed = array_values(array_filter(array_column($offices, 'area')));
            $area = $placed ? $this->tree->commonAncestor($placed) : ($scope->areas ? $this->tree->commonAncestor($scope->areas) : $root);
            // An office placed on the common area itself would only show as "at this level": open one level up
            // so that area is drawn and coloured.
            if ($placed && in_array($area, $placed, true) && ($parent = $this->tree->node($area)['parent'] ?? null)) {
                $area = $parent;
            }
        } else {
            $area = $this->tree->node((int) $requested) ? (int) $requested : $root;
        }
        // Open the nearest area that has a children file (a union or a city corporation cannot be opened).
        foreach (array_reverse($this->tree->chain((int) $area)) as $candidate) {
            if ($this->boundaries->hasChildrenFile($candidate) || $candidate === $root) {
                return $candidate;
            }
        }

        return (int) $root;
    }

    /** Child areas the view shows: administrative children down to unions (no wards). */
    private function children(int $area, string $urban): array
    {
        $level = $this->tree->node($area)['level'] ?? 0;
        if ($level >= 4) {
            return [];
        }

        return array_values(array_filter($this->tree->childIds($area), function ($id) use ($urban) {
            $node = $this->tree->node($id);
            if ($node['level'] > 4 || in_array($node['type'], ['pouro_ward', 'thana_ward', 'city_thana'], true)) {
                return false;
            }

            return ! ($urban === 'hide' && $this->tree->isUrban($id));
        }));
    }

    /** @return array<string, bool> */
    private function applicability(array $layers, array $offices, bool $office): array
    {
        $out = [];
        foreach ($layers as $layer) {
            $flag = $layer->applicable;
            if ($flag === null) {
                $flag = true;
                if ($office && $layer->aggregation === 'ratio') {
                    $den = 0;
                    foreach ($offices as $o) {
                        $v = $o['values'][$layer->key] ?? null;
                        $den += is_array($v) ? (float) ($v[1] ?? 0) : 0;
                    }
                    $flag = $den > 0;   // no denominator anywhere (product-based plans): not applicable
                }
            }
            $out[$layer->key] = $flag;
        }

        return $out;
    }

    /** @return array{0: LayerDefinition, 1: ?string} chosen layer and the requested/first-choice key */
    private function chooseLayer(array $layers, array $applicable, GeoRequest $request): array
    {
        $chain = array_values(array_filter(array_merge([$request->layer], $request->fallback), fn ($k) => $k && $this->layerByKey($layers, $k)));
        foreach ($chain as $key) {
            if ($applicable[$key] ?? false) {
                return [$this->layerByKey($layers, $key), $chain[0]];
            }
        }
        $layer = $chain ? $this->layerByKey($layers, $chain[0]) : $layers[0];

        return [$layer, $chain[0] ?? null];
    }

    private function layerByKey(array $layers, ?string $key): ?LayerDefinition
    {
        foreach ($layers as $layer) {
            if ($layer->key === $key) {
                return $layer;
            }
        }

        return null;
    }

    private function officeEntry(array $entry, array $offices, array $ids, LayerDefinition $layer): array
    {
        $entry['offices'] = count($ids);
        if (! $ids) {
            return $entry + ['value' => null];
        }
        $agg = $this->aggregator->aggregate($offices, $ids, $layer);
        if ($layer->suppressBelow !== null && count($ids) < $layer->suppressBelow) {
            return array_merge($entry, ['state' => 'suppressed', 'value' => null]);
        }
        if ($layer->aggregation === 'ratio') {
            $entry['num'] = $this->number($agg['num']);
            $entry['den'] = $this->number($agg['den']);
            $entry['state'] = $agg['den'] > 0 ? 'ok' : 'not_applicable';
        } else {
            $entry['state'] = $agg['value'] === null ? 'no_data' : 'ok';
        }
        $entry['value'] = $entry['state'] === 'ok' ? $this->number($agg['value']) : null;
        if ($entry['value'] !== null && ($display = $layer->display($entry['value'])) !== null) {
            $entry['display'] = $display;
        }

        return $entry;
    }

    private function number(int|float|null $v): int|float|null
    {
        if ($v === null) {
            return null;
        }
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (int) $v;
        }

        return is_float($v) ? round($v, 6) : $v;
    }
}
