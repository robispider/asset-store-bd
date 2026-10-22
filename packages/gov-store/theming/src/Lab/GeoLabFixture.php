<?php

namespace GovStore\Theming\Lab;

/**
 * Fixture geography for the Lab's geo view demos: a made-up country of 4 regions × 3 districts × 3 sub-areas
 * on a jittered grid (shared borders match exactly, like the real national frame), synthetic values and a
 * gs-geo-data/1 payload builder. No GovStore data, so the kit can be reviewed on its own (plan D1).
 */
final class GeoLabFixture
{
    public const VERSION = 'lab-fixture-1';

    public const ROOT = 1;

    private const W = 1800;

    private const H = 1200;

    private const STEP = 100;

    /** @return array<int, array{id:int, parent:?int, level:int, rect:array{0:int,1:int,2:int,3:int}, en:string, bn:string}> */
    public static function tree(): array
    {
        static $tree = null;
        if ($tree !== null) {
            return $tree;
        }
        $tree = [self::ROOT => ['id' => self::ROOT, 'parent' => null, 'level' => 0, 'rect' => [0, 0, self::W, self::H], 'en' => 'Fixtureland', 'bn' => 'নমুনাদেশ']];
        $regions = [['North', 'উত্তর'], ['East', 'পূর্ব'], ['West', 'পশ্চিম'], ['South', 'দক্ষিণ']];
        foreach ($regions as $r => [$en, $bn]) {
            $rid = 10 + $r;
            $rx = ($r % 2) * 900;
            $ry = intdiv($r, 2) * 600;
            $tree[$rid] = ['id' => $rid, 'parent' => self::ROOT, 'level' => 1, 'rect' => [$rx, $ry, $rx + 900, $ry + 600], 'en' => $en, 'bn' => $bn];
            for ($d = 0; $d < 3; $d++) {
                $did = 100 + $r * 10 + $d;
                $dx = $rx + $d * 300;
                $tree[$did] = ['id' => $did, 'parent' => $rid, 'level' => 2, 'rect' => [$dx, $ry, $dx + 300, $ry + 600], 'en' => $en.' '.($d + 1), 'bn' => $bn.' '.self::bnDigit($d + 1)];
                for ($s = 0; $s < 3; $s++) {
                    $sid = 1000 + $r * 100 + $d * 10 + $s;
                    $sy = $ry + $s * 200;
                    $tree[$sid] = ['id' => $sid, 'parent' => $did, 'level' => 3, 'rect' => [$dx, $sy, $dx + 300, $sy + 200],
                        'en' => $en.' '.($d + 1).chr(97 + $s), 'bn' => $bn.' '.self::bnDigit($d + 1).['ক', 'খ', 'গ'][$s]];
                }
            }
        }

        return $tree;
    }

    /** children/<id>.json in the boundary file contract, or null when the area has no children. */
    public static function childrenFile(int $id): ?array
    {
        $tree = self::tree();
        if (! isset($tree[$id])) {
            return null;
        }
        $kids = array_values(array_filter($tree, fn ($n) => $n['parent'] === $id));
        if (! $kids) {
            return null;
        }
        [$x0, $y0, $x1, $y1] = $tree[$id]['rect'];
        $pad = 20;

        return [
            'version' => self::VERSION,
            'id' => $id,
            'viewBox' => ($x0 - $pad).' '.($y0 - $pad).' '.($x1 - $x0 + 2 * $pad).' '.($y1 - $y0 + 2 * $pad),
            'outline' => self::path($tree[$id]['rect']),
            'areas' => array_map(function ($n) {
                $points = self::points($n['rect']);
                $xs = array_column($points, 0);
                $ys = array_column($points, 1);

                return [
                    'id' => $n['id'],
                    'kind' => $n['level'] === 3 && $n['id'] % 10 === 2 && intdiv($n['id'], 10) % 10 === 1 ? 'urban' : 'area',
                    'path' => self::path($n['rect']),
                    'bbox' => [min($xs), min($ys), max($xs) - min($xs), max($ys) - min($ys)],
                    'label' => [intdiv($n['rect'][0] + $n['rect'][2], 2), intdiv($n['rect'][1] + $n['rect'][3], 2)],
                ];
            }, $kids),
        ];
    }

    /** flat/level-<n>.json: every area at one level across the fixture country (flat mode). */
    public static function flatFile(int $level): ?array
    {
        $nodes = array_values(array_filter(self::tree(), fn ($n) => $n['level'] === $level));
        if (! $nodes || $level < 1) {
            return null;
        }
        $root = self::childrenFile(self::ROOT);
        $areas = [];
        foreach ($nodes as $n) {
            $file = self::childrenFile($n['parent']);
            foreach ($file['areas'] as $a) {
                if ($a['id'] === $n['id']) {
                    $areas[] = $a + ['parent' => $n['parent']];
                }
            }
        }

        return ['version' => self::VERSION, 'level' => $level, 'id' => self::ROOT, 'viewBox' => $root['viewBox'], 'outline' => $root['outline'], 'areas' => $areas];
    }

    /** Rows shaped like gov.geo.search with parent ids, for the find box. */
    public static function search(string $q, string $locale): array
    {
        $bn = str_starts_with($locale, 'bn');
        $out = [];
        foreach (self::tree() as $id => $n) {
            $name = $bn ? $n['bn'] : $n['en'];
            if ($n['level'] > 0 && mb_stripos($name, $q) !== false) {
                $out[] = ['id' => $id, 'text' => $name, 'geo_type_label' => ['', 'Region', 'District', 'Sub-area'][$n['level']], 'parent_id' => $n['parent'], 'level' => $n['level'], 'trail' => []];
            }
        }

        return array_slice($out, 0, 10);
    }

    /** Synthetic value in [0.03, 0.98], correlated with the parent's value. */
    public static function value(int $id, string $layer): ?float
    {
        $node = self::tree()[$id] ?? null;
        if (! $node) {
            return null;
        }
        $base = $node['parent'] ? self::value($node['parent'], 'progress') : 0.52;
        $v = $node['parent'] ? max(0.03, min(0.98, 0.55 * $base + 0.45 * self::hash($id))) : 0.52;

        return $layer === 'assets' ? round($v * 9000 * (4.5 ** (3 - $node['level']))) : round($v, 4);
    }

    /**
     * gs-geo-data/1 for one area. $states adds every area state for review: one region outside access, one
     * partly inside, a district without data, one not applicable and one without a shape.
     */
    public static function payload(int $area, string $layer, bool $states, string $locale, string $source = 'lab.synthetic', ?int $level = null): ?array
    {
        $tree = self::tree();
        $flat = $level !== null && $area === self::ROOT && self::flatFile($level) ? $level : null;
        if (! isset($tree[$area]) || (! $flat && ! self::childrenFile($area))) {
            return null;
        }
        $bn = str_starts_with($locale, 'bn');
        $name = fn ($id) => $bn ? $tree[$id]['bn'] : $tree[$id]['en'];
        $layer = in_array($layer, ['progress', 'assets'], true) ? $layer : 'progress';
        $areas = [];
        $values = [];
        foreach (array_filter($tree, fn ($n) => $flat ? $n['level'] === $flat : $n['parent'] === $area) as $id => $node) {
            $state = 'ok';
            if ($states) {
                $state = match ($id) {
                    13 => 'no_access',
                    12 => 'partial',
                    101 => 'no_data',
                    111 => 'not_applicable',
                    default => 'ok',
                };
            }
            $value = in_array($state, ['ok', 'partial'], true) ? self::value($id, $layer) : null;
            $entry = ['state' => $state, 'name' => $name($id), 'drillable' => $state !== 'no_access' && $node['level'] < 3, 'shape' => true];
            if ($state !== 'no_access') {
                $entry += ['value' => $value, 'offices' => 1 + ($id % 3)];
            }
            if ($state === 'partial') {
                $entry['partial'] = true;
            }
            if ($value !== null) {
                $values[] = $value;
            }
            $areas[(string) $id] = $entry;
        }
        $chain = [];
        for ($c = $area; $c !== null; $c = $tree[$c]['parent']) {
            array_unshift($chain, ['id' => $c, 'name' => $name($c)]);
        }
        $labels = ['progress' => $bn ? 'যাচাই অগ্রগতি (কৃত্রিম)' : 'Verification progress (synthetic)', 'assets' => $bn ? 'নিবন্ধিত সম্পদ (কৃত্রিম)' : 'Assets registered (synthetic)'];

        return [
            'contract' => 'gs-geo-data/1',
            'source' => $source,
            'area' => ['id' => $area, 'level' => $tree[$area]['level'], 'name' => $name($area), 'parent' => $tree[$area]['parent'], 'trail' => $chain],
            'level' => $flat ?? $tree[$area]['level'] + 1,
            'flat' => $flat,
            'boundary' => ['version' => self::VERSION, 'children' => $flat ? 'flat/level-'.$flat.'.json' : 'children/'.$area.'.json'],
            'layer' => ['key' => $layer, 'label' => $labels[$layer], 'format' => $layer === 'progress' ? 'percent' : 'integer', 'aggregation' => 'area'],
            'layers' => [['key' => 'progress', 'label' => $labels['progress'], 'applicable' => true], ['key' => 'assets', 'label' => $labels['assets'], 'applicable' => true]],
            'stats' => ['min' => $values ? min($values) : null, 'max' => $values ? max($values) : null, 'national' => self::value(self::ROOT, $layer), 'total' => self::value($area, $layer)],
            'areas' => $areas,
            'here' => ['offices' => $area === 10 ? 1 : 0],
            'unplaced' => ['offices' => $area === self::ROOT && $states ? 2 : 0],
            'synthetic' => true,
            'attribution' => $bn ? 'নমুনা আকার, কৃত্রিম মান' : 'Fixture shapes, synthetic values',
        ];
    }

    /** Side-panel rows for the details endpoint. */
    public static function details(int $area, string $locale, string $layer = 'progress'): array
    {
        $tree = self::tree();
        $bn = str_starts_with($locale, 'bn');
        $offices = [];
        foreach ($tree as $id => $node) {
            if ($node['level'] === 3 && ($id === $area || self::isUnder($id, $area)) && $id % 2 === 0) {
                $offices[] = ['id' => $id, 'name' => ($bn ? 'কার্যালয় ' : 'Office ').($bn ? $node['bn'] : $node['en']), 'area' => $id,
                    'area_name' => $bn ? $node['bn'] : $node['en'], 'value' => self::value($id, $layer === 'assets' ? 'assets' : 'progress'), 'link' => null];
            }
        }

        return ['area' => $area, 'offices' => array_slice($offices, 0, 12)];
    }

    /** A resolved gs-geo-config/1 for a Lab demo. */
    public static function config(string $id, string $intent, array $endpoints, string $locale, array $strings, array $overrides = []): array
    {
        $scale = [
            'progress' => ['type' => 'sequential', 'steps' => 5, 'domain' => [0, 1]],
            'compare' => ['type' => 'diverging', 'steps' => 6, 'reference' => 'national'],
            'pick' => ['type' => 'none'],
        ][$intent] ?? ['type' => 'quantile', 'steps' => 5, 'domain' => 'auto'];
        $config = [
            'contract' => 'gs-geo-config/1',
            'id' => $id,
            'preset' => null,
            'intent' => $intent,
            'locale' => $locale,
            'source' => ['key' => 'lab.synthetic', 'mode' => 'remote', 'params' => ['states' => $intent === 'progress' ? 1 : 0]],
            'layers' => ['default' => ['progress'], 'allow' => $intent === 'pick' ? ['progress'] : ['progress', 'assets']],
            'levels' => ['start' => 'root', 'root' => self::ROOT, 'min' => 1, 'max' => 3, 'urban' => 'colour', 'flat' => $intent === 'progress' ? 2 : null],
            'interaction' => [
                'click' => $intent === 'compare' ? 'select' : ($intent === 'pick' ? 'select' : 'drill'),
                'leaf_click' => $intent === 'pick' ? 'select' : 'details',
                'wheel' => 'modifier',
                'select' => $intent === 'compare' ? 'multi' : 'single',
                'touch' => 'tap-select-tap-drill',
            ],
            'toolbar' => ['zoom' => true, 'reset' => true, 'layers' => $intent !== 'pick', 'filters' => false, 'search' => $intent !== 'compare', 'table' => $intent !== 'pick', 'export' => $intent === 'progress', 'fullscreen' => $intent === 'progress', 'help' => true],
            'panels' => ['breadcrumb' => true, 'legend' => true, 'details' => 'side', 'table' => $intent === 'pick' ? 'none' : 'toggle', 'labels' => true, 'markers' => $intent === 'progress'],
            'scale' => $scale,
            'size' => ['height' => 380, 'aspect' => null],
            'url_state' => false,
            'locked' => ['source.key'],
            'bind_filters' => null,
            'input' => $intent === 'pick' ? ['name' => $id.'-area', 'value' => null] : null,
            'boundary' => ['version' => self::VERSION, 'base_url' => $endpoints['boundary'], 'root' => self::ROOT, 'attribution' => 'Fixture shapes'],
            'endpoints' => ['data' => $endpoints['data'], 'details' => $endpoints['details'], 'children' => null, 'search' => $endpoints['search'] ?? null],
            'strings' => $strings,
        ];

        return array_replace_recursive($config, $overrides);
    }

    private static function isUnder(int $id, int $ancestor): bool
    {
        $tree = self::tree();
        for ($c = $tree[$id]['parent'] ?? null; $c !== null; $c = $tree[$c]['parent']) {
            if ($c === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /** Grid points along a rectangle's edges, jittered by position so neighbours share identical borders. */
    private static function points(array $rect): array
    {
        [$x0, $y0, $x1, $y1] = $rect;
        $out = [];
        foreach ([[$x0, $y0, $x1, $y0], [$x1, $y0, $x1, $y1], [$x1, $y1, $x0, $y1], [$x0, $y1, $x0, $y0]] as [$ax, $ay, $bx, $by]) {
            $n = max(1, intdiv(abs($bx - $ax) + abs($by - $ay), self::STEP));
            for ($i = 0; $i < $n; $i++) {
                $x = $ax + intdiv(($bx - $ax) * $i, $n);
                $y = $ay + intdiv(($by - $ay) * $i, $n);
                $out[] = self::jitter($x, $y);
            }
        }

        return $out;
    }

    private static function path(array $rect): string
    {
        $points = self::points($rect);

        return 'M'.implode('L', array_map(fn ($p) => $p[0].' '.$p[1], $points)).'Z';
    }

    private static function jitter(int $x, int $y): array
    {
        $corner = ($x === 0 || $x === self::W) && ($y === 0 || $y === self::H);
        if ($corner) {
            return [$x, $y];
        }
        $j = fn ($salt) => (int) round((self::hash($x * 7919 + $y * 104729 + $salt) - 0.5) * 60);

        return [$x + ($x === 0 || $x === self::W ? $j(1) / 2 : $j(1)), $y + ($y === 0 || $y === self::H ? $j(2) / 2 : $j(2))];
    }

    private static function hash(int $n): float
    {
        $x = ($n * 2654435761) & 0xFFFFFFFF;
        $x ^= $x >> 13;
        $x = ($x * 1274126177) & 0xFFFFFFFF;
        $x ^= $x >> 16;

        return $x / 4294967296;
    }

    private static function bnDigit(int $d): string
    {
        return strtr((string) $d, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
