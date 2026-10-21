<?php

namespace GovStore\GeoView\Services;

use GovStore\GeoAreas\Services\GeoTree;
use GovStore\GeoView\Data\LayerDefinition;

/**
 * grain=office aggregation (plan §6.3). For the viewed area A, each placed office falls into the child of A on
 * its chain, into `here` when it sits on A itself, nowhere when it lies outside A, and into `unplaced` when it
 * has no area. Ratio layers roll up as Σnum/Σden; sum and count add; max and min take the extreme.
 */
final class GeoAggregator
{
    public function __construct(private GeoTree $tree) {}

    /**
     * @param  array<int, array{area:?int, values:array}>  $offices  location id → placed area and layer values
     * @param  int[]  $children  child area ids of A that the view shows
     * @return array{children: array<int, int[]>, here: int[], unplaced: int[], inside: int[]}
     */
    public function bucket(array $offices, int $area, array $children, string $urban = 'colour', ?int $level = null): array
    {
        $childSet = array_flip($children);
        $out = ['children' => array_fill_keys($children, []), 'here' => [], 'unplaced' => [], 'inside' => []];
        foreach ($offices as $id => $office) {
            $placed = $office['area'];
            if ($placed === null || ! $this->tree->node($placed)) {
                $out['unplaced'][] = $id;

                continue;
            }
            // urban=hide: the city corporation or paurashava is not drawn, so its offices count in the parent area.
            if ($urban === 'hide') {
                $placed = $this->tree->rollup($placed) ?? $placed;
            }
            $chain = $this->tree->chain($placed);
            $at = array_search($area, $chain, true);
            if ($at === false) {
                continue;
            }
            $out['inside'][] = $id;
            if ($at === count($chain) - 1) {
                $out['here'][] = $id;

                continue;
            }
            // Flat mode: the shown areas are the descendants at one level, not the direct children.
            $child = $level === null ? $chain[$at + 1] : (array_values(array_filter($chain, fn ($id) => ($this->tree->node($id)['level'] ?? -1) === $level))[0] ?? null);
            if ($child === null) {
                $out['here'][] = $id;

                continue;
            }
            if (isset($childSet[$child])) {
                $out['children'][$child][] = $id;
            } else {
                // A child the view does not draw (a ward): count the office at this level.
                $out['here'][] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{values:array}>  $offices
     * @param  int[]  $ids
     * @return array{value: int|float|null, num?: int|float, den?: int|float, count: int}
     */
    public function aggregate(array $offices, array $ids, LayerDefinition $layer): array
    {
        $values = [];
        foreach ($ids as $id) {
            $v = $offices[$id]['values'][$layer->key] ?? null;
            if ($v !== null) {
                $values[] = $v;
            }
        }
        $count = count($values);
        switch ($layer->aggregation) {
            case 'ratio':
                $num = 0;
                $den = 0;
                foreach ($values as $v) {
                    if (is_array($v)) {
                        $num += (float) ($v[0] ?? 0);
                        $den += (float) ($v[1] ?? 0);
                    }
                }

                return ['value' => $den > 0 ? $num / $den : null, 'num' => $num, 'den' => $den, 'count' => $count];
            case 'max':
                return ['value' => $values ? max(array_map('floatval', $values)) : null, 'count' => $count];
            case 'min':
                return ['value' => $values ? min(array_map('floatval', $values)) : null, 'count' => $count];
            case 'count':
                return ['value' => $count ? count(array_filter($values, fn ($v) => (bool) $v)) : null, 'count' => $count];
            default:
                return ['value' => $values ? array_sum(array_map(fn ($v) => is_array($v) ? (float) $v[0] : (float) $v, $values)) : null, 'count' => $count];
        }
    }
}
