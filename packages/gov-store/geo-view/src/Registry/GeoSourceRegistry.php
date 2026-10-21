<?php

namespace GovStore\GeoView\Registry;

use GovStore\GeoView\Contracts\GeoDataSource;
use InvalidArgumentException;

/**
 * Data sources registered by domain packages (like MetadataRegistry). Classes are resolved lazily, so a
 * registration costs nothing on pages without a map.
 */
final class GeoSourceRegistry
{
    /** @var array<string, class-string<GeoDataSource>|GeoDataSource> pending class names or resolved instances */
    private array $sources = [];

    /** @param  class-string<GeoDataSource>|GeoDataSource  $source */
    public function register(string|GeoDataSource $source, ?string $key = null): self
    {
        if (is_string($source) && ! is_subclass_of($source, GeoDataSource::class)) {
            throw new InvalidArgumentException("[{$source}] does not implement GeoDataSource.");
        }
        $key ??= is_string($source) ? app($source)->key() : $source->key();
        $this->sources[$key] = $source;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): ?GeoDataSource
    {
        if (! isset($this->sources[$key])) {
            return null;
        }
        if (is_string($this->sources[$key])) {
            $this->sources[$key] = app($this->sources[$key]);
        }

        return $this->sources[$key];
    }

    /** @return string[] */
    public function keys(): array
    {
        $keys = array_keys($this->sources);
        sort($keys);

        return $keys;
    }
}
