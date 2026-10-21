<?php

namespace GovStore\GeoView\Registry;

/** Presets (a source's default configuration, plan §5.1 layer 3) registered by the source's package. */
final class GeoPresetRegistry
{
    /** @var array<string, array> */
    private array $presets = [];

    public function merge(array $presets): self
    {
        foreach ($presets as $key => $preset) {
            $this->presets[(string) $key] = (array) $preset;
        }

        return $this;
    }

    public function get(string $key): ?array
    {
        return $this->presets[$key] ?? null;
    }

    public function all(): array
    {
        ksort($this->presets);

        return $this->presets;
    }
}
