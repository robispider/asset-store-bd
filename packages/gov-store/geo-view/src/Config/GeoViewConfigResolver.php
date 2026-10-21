<?php

namespace GovStore\GeoView\Config;

use GovStore\GeoAreas\Services\BoundarySet;
use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Support\ListValue;
use GovStore\GeoView\Support\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Merges resolution layers 1–4 (engine defaults → intent → preset → host, plan §5.1) into a gs-geo-config/1
 * and validates it. Keys locked by an earlier layer cannot be changed by a later one. An unknown key throws in
 * local and testing environments and is dropped with a warning in production. No layer can widen access:
 * the config is a convenience, the data API decides what is visible.
 */
final class GeoViewConfigResolver
{
    public function __construct(private GeoPresetRegistry $presets, private BoundarySet $boundaries) {}

    /**
     * @param  array  $host  host attributes: id, intent, params, bind_filters, input, plus override arrays
     *                       (levels, toolbar, panels, interaction, scale, size, layers, url_state)
     * @return array{config: array, blocked: array<int, array{key:string, layer:string, lock:string}>}
     */
    public function resolve(?string $preset, array $host): array
    {
        $presetConfig = $preset !== null ? $this->presets->get($preset) : [];
        if ($presetConfig === null) {
            throw new InvalidArgumentException("Unknown geo view preset [{$preset}].");
        }
        $presetConfig = $this->normalisePreset($presetConfig);
        $intent = $host['intent'] ?? $presetConfig['intent'] ?? config('geo-view.defaults.intent');
        if (! in_array($intent, Intents::ALL, true)) {
            throw new InvalidArgumentException("Unknown geo view intent [{$intent}].");
        }
        $params = (array) Arr::pull($host, 'params', []);
        $layers = [
            'defaults' => config('geo-view.defaults'),
            'intent' => Intents::defaults($intent),
            'preset' => $presetConfig,
            'host' => $host,
        ];
        $config = [];
        $locks = [];
        $blocked = [];
        foreach ($layers as $name => $layer) {
            foreach (Arr::dot($this->dotSafe($layer)) as $key => $value) {
                if ($key === 'locked' || str_starts_with($key, 'locked.')) {
                    continue;
                }
                $lock = $this->lockFor($locks, $key);
                if ($lock !== null) {
                    $blocked[] = ['key' => $key, 'layer' => $name, 'lock' => $lock];

                    continue;
                }
                Arr::set($config, $key, $this->restore($value));
            }
            foreach ((array) ($layer['locked'] ?? []) as $lock) {
                $locks[$lock] ??= $name;
            }
        }
        $config['intent'] = $intent;
        $config['locked'] = array_keys($locks);
        $config['contract'] = 'gs-geo-config/1';
        $config['preset'] = $preset;
        $config['id'] = (string) ($host['id'] ?? str_replace('.', '-', (string) $preset) ?: 'geo-map');
        $config['locale'] = app()->getLocale();

        // Host params: only keys the preset declares (source.param_keys), never the source key itself.
        $allowed = (array) ($config['source']['param_keys'] ?? []);
        $config['source']['params'] = array_filter(
            array_intersect_key($params, array_flip($allowed)),
            fn ($v) => $v !== null && $v !== '' && (is_scalar($v)),
        );
        $config['boundary'] = [
            'version' => $this->boundaries->version(),
            'base_url' => $this->boundaries->baseUrl(),
            'root' => (int) ($this->boundaries->root() ?? 0),
            'attribution' => $this->boundaries->attribution(),
        ];
        $config['levels']['root'] ??= $config['boundary']['root'] ?: null;
        $key = $config['source']['key'] ?? null;
        $config['endpoints'] = [
            'data' => $key ? $this->path(route('gov.geo-view.data', $key)) : null,
            'details' => $key ? $this->path(route('gov.geo-view.details', $key)) : null,
            'children' => $this->path(route('gov.geo.children', ['area' => 0])),
            'search' => $this->path(route('gov.geo.search')),
        ];
        $config['endpoints']['children'] = str_replace('/0/children', '/{area}/children', $config['endpoints']['children']);
        $strings = trans('gs-theme::kit.geo');
        $config['strings'] = is_array($strings) ? $strings : [];

        return ['config' => $this->validated($config), 'blocked' => $blocked];
    }

    /** Preset format (§5.3): source.params lists the allowed host params; config keeps them as param_keys. */
    private function normalisePreset(array $preset): array
    {
        if (isset($preset['source']['params']) && array_is_list($preset['source']['params'])) {
            $preset['source']['param_keys'] = $preset['source']['params'];
            unset($preset['source']['params']);
        }
        unset($preset['title'], $preset['description']);

        return $preset;
    }

    private function validated(array $config): array
    {
        $schema = JsonSchema::contract('gs-geo-config.schema.json');
        $errors = JsonSchema::validate($schema, $config);
        if ($errors) {
            if (app()->environment(['local', 'testing'])) {
                throw new InvalidArgumentException('Invalid geo view config: '.implode('; ', array_slice($errors, 0, 5)));
            }
            Log::warning('geo-view: config does not match gs-geo-config/1; unknown keys dropped', ['id' => $config['id'], 'errors' => array_slice($errors, 0, 10)]);
            $config = JsonSchema::prune($schema, $config);
        }

        return $config;
    }

    private function lockFor(array $locks, string $key): ?string
    {
        foreach (array_keys($locks) as $lock) {
            if ($key === $lock || str_starts_with($key, $lock.'.')) {
                return $lock;
            }
        }

        return null;
    }

    /** Lists (layers.default, scale.domain…) are values, not paths: wrap them so Arr::dot keeps them whole. */
    private function dotSafe(array $layer): array
    {
        foreach ($layer as $key => $value) {
            if (is_array($value)) {
                $layer[$key] = array_is_list($value) ? new ListValue($value) : $this->dotSafe($value);
            }
        }

        return $layer;
    }

    private function restore(mixed $value): mixed
    {
        return $value instanceof ListValue ? $value->items : $value;
    }

    private function path(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }
}
