<?php

namespace GovStore\GeoView\View\Components;

use GovStore\GeoView\Config\GeoViewConfigResolver;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Services\GeoDataService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * <x-geoview::map id="programme-map" preset="tracking.programme" :params="[...]" bind-filters=".form" />
 *
 * Resolves the config on the server, computes the first payload in-process (no request waterfall, plan §6.6) and
 * renders the same rows as a server table: the no-JS, print and screen-reader fallback.
 */
class Map extends Component
{
    public array $config;

    public ?array $payload = null;

    public function __construct(
        public ?string $preset = null,
        public ?string $id = null,
        public ?string $intent = null,
        public array $params = [],
        public ?string $bindFilters = null,
        public ?string $name = null,
        public ?int $value = null,
        public array $options = [],
        public ?string $label = null,
    ) {
        $host = array_filter([
            'id' => $id,
            'intent' => $intent,
            'params' => $params,
            'bind_filters' => $bindFilters,
            'input' => $name ? ['name' => $name, 'value' => $value] : null,
        ], fn ($v) => $v !== null && $v !== []) + $options;
        $this->config = app(GeoViewConfigResolver::class)->resolve($preset, $host)['config'];
        $this->payload = $this->firstPayload();
    }

    private function firstPayload(): ?array
    {
        $key = $this->config['source']['key'] ?? null;
        $source = $key ? app(GeoSourceRegistry::class)->get($key) : null;
        $user = auth()->user();
        if (! $source || ! $user || ! Gate::forUser($user)->allows($source->ability())) {
            return null;
        }
        $request = new GeoRequest(
            user: $user,
            params: $this->config['source']['params'] ?? [],
            area: $this->startArea(),
            fallback: (array) ($this->config['layers']['default'] ?? []),
            locale: app()->getLocale(),
            urban: $this->config['levels']['urban'] ?? 'colour',
        );
        try {
            validator($request->params, $source->rules())->validate();
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }
        if (! $source->authorize($request)) {
            return null;
        }

        return app(GeoDataService::class)->payload($source, $request);
    }

    private function startArea(): int|string
    {
        $start = $this->config['levels']['start'] ?? 'auto';

        return match (true) {
            is_int($start) => $start,
            $start === 'root' => (int) ($this->config['levels']['root'] ?? 0) ?: 'auto',
            default => 'auto',
        };
    }

    public function render()
    {
        return view('geoview::components.map');
    }
}
