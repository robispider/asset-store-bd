<?php

namespace GovStore\GeoView\Data;

use Illuminate\Contracts\Auth\Authenticatable;

/** One validated request to a data source: who asks, for which params, area, layers and filters. */
final class GeoRequest
{
    /**
     * @param  array<string, mixed>  $params  host params, validated by the source's rules()
     * @param  int|string  $area  GeoAreaId or 'auto'
     * @param  string[]  $fallback  layer chain when no layer is chosen (config layers.default)
     * @param  array<string, string>  $filters  source-declared filters
     */
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly array $params = [],
        public readonly int|string $area = 'auto',
        public readonly ?string $layer = null,
        public readonly array $fallback = [],
        public readonly array $filters = [],
        public readonly ?string $locale = null,
        public readonly string $urban = 'colour',
    ) {}

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function locale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    public function withArea(int|string $area): self
    {
        return new self($this->user, $this->params, $area, $this->layer, $this->fallback, $this->filters, $this->locale, $this->urban);
    }

    /** Cache key part for facts: everything that changes them except the area. */
    public function factsKey(): string
    {
        $params = $this->params;
        $filters = $this->filters;
        ksort($params);
        ksort($filters);

        return sha1(json_encode([$params, $filters]));
    }
}
